document.addEventListener('DOMContentLoaded', () => {
  const debounce = (fn, wait = 250) => {
    let t;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...args), wait);
    };
  };

  document.querySelectorAll('.tag-editor').forEach(editor => {
    const name = editor.dataset.name || 'tags';
    const form = editor.closest('form') || document;
    let hidden = form.querySelector(`input[name="${name}"]`);
    if (!hidden) {
      hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = name;
      form.appendChild(hidden);
    }

    const container = document.createElement('div');
    container.className = 'tag-editor-container';

    const chips = document.createElement('div');
    chips.className = 'tag-chips';

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'tag-input-field';
    input.placeholder = editor.dataset.placeholder || 'Adicione tags...';
    input.autocomplete = 'off';

    const suggestionsBox = document.createElement('div');
    suggestionsBox.className = 'tag-suggestions';

    container.appendChild(chips);
    container.appendChild(input);
    container.appendChild(suggestionsBox);
    editor.appendChild(container);

    let tagsArray = [];
    tagsArray = hidden.value ? hidden.value.split(',').map(t => t.trim()).filter(Boolean) : [];
    let lastAddedTag = null;

    function render() {
      chips.innerHTML = '';
      tagsArray.forEach((t, index) => {
        const span = document.createElement('span');
        span.className = 'tag-chip';
        
        // Aplica a classe de animação se for a tag recém-adicionada
        if (t === lastAddedTag) {
          span.classList.add('tag-pulse');
        }

        const textNode = document.createElement('span');
        textNode.className = 'tag-text-label';
        textNode.textContent = t;
        textNode.style.cursor = 'pointer';
        textNode.title = 'Clique para editar esta tag';
        
        textNode.addEventListener('click', () => {
          // Criar o input de edição inline
          const inputEdit = document.createElement('input');
          inputEdit.type = 'text';
          inputEdit.value = t;
          inputEdit.className = 'tag-edit-input';
          
          // Estilização básica para o input se integrar ao chip
          inputEdit.style.width = `${Math.max(t.length, 2)}ch`;
          
          span.classList.add('editing'); // Classe para mudar o fundo via CSS
          btn.style.display = 'none';    // Esconde o 'X' durante a edição

          span.replaceChild(inputEdit, textNode);
          inputEdit.focus();
          inputEdit.select();

          const finishEdit = () => {
            const newValue = inputEdit.value.trim();
            if (newValue === "") {
              // Se o campo for esvaziado, remove a tag direto no campo
              tagsArray.splice(index, 1);
            } else if (newValue !== t) {
              tagsArray[index] = newValue;
            }
            render(); // Re-renderiza para voltar ao estado normal
          };

          inputEdit.addEventListener('blur', finishEdit);
          inputEdit.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); finishEdit(); }
            if (e.key === 'Escape') { e.preventDefault(); render(); }
          });
        });

        span.appendChild(textNode);

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'tag-remove';
        btn.innerHTML = '✕';
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          // Filtra o array local deste editor
          tagsArray = tagsArray.filter(item => item !== t);
          render();
        });

        span.appendChild(btn);
        chips.appendChild(span);
      });
      // Garante que se o array estiver vazio, o campo hidden envie uma string vazia para o PHP
      hidden.value = tagsArray.length > 0 ? tagsArray.join(', ') : '';
      suggestionsBox.innerHTML = '';
    }

    function addTag(raw) {
      const tag = raw.trim();
      if (!tag) return;
      if (!tagsArray.includes(tag)) {
        lastAddedTag = tag; // Marca a tag para animar
        tagsArray.push(tag);
        render();
        lastAddedTag = null; // Limpa a marcação após renderizar
      }
      input.value = '';
      input.focus();
    }

    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        addTag(input.value.replace(/,$/, ''));
      } else if (e.key === 'Backspace' && input.value === '') {
        e.preventDefault();
        tagsArray.pop();
        render();
      }
    });

    const doSuggest = debounce(async (val) => {
      suggestionsBox.innerHTML = '';
      const q = val.trim();
      if (q.length < 2) return;
      try {
        const res = await fetch(`buscar_tags.php?q=${encodeURIComponent(q)}`);
        if (!res.ok) return;
        const list = await res.json();
        if (list.length === 0) return;
        list.forEach(item => {
          const s = document.createElement('div');
          s.className = 'tag-suggestion';
          s.textContent = item;
          s.addEventListener('click', () => addTag(item));
          suggestionsBox.appendChild(s);
        });
      } catch (e) {
        console.error('Erro ao buscar tags:', e);
      }
    }, 200);

    input.addEventListener('input', (e) => {
      doSuggest(e.target.value);
    });

    document.addEventListener('click', (ev) => {
      if (!editor.contains(ev.target)) {
        suggestionsBox.innerHTML = '';
      }
    });

    render();

    if (form.tagName === 'FORM') {
      form.addEventListener('submit', () => {
        hidden.value = tagsArray.join(', ');
      });
    }
  });
});