document.addEventListener('DOMContentLoaded', () => {
    // --- Lógica das Abas ---
    const tabs = document.querySelectorAll('.tab-btn');
    const contents = document.querySelectorAll('.tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.tab;

            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));

            tab.classList.add('active');
            document.getElementById(target).classList.add('active');
        });
    });

    // --- Lógica do Botão "Ler mais" na Descrição ---
    const textoDesc = document.getElementById('texto-descricao');
    const btnLerMais = document.getElementById('btn-ler-mais-desc');

    if (textoDesc && btnLerMais) {
        // Verifica se o texto é longo o suficiente para ter sido cortado
        if (textoDesc.scrollHeight > textoDesc.clientHeight) {
            btnLerMais.classList.remove('d-none');
        }

        btnLerMais.addEventListener('click', () => {
            if (textoDesc.classList.contains('descricao-curta')) {
                textoDesc.classList.remove('descricao-curta');
                textoDesc.classList.add('descricao-expandida');
                btnLerMais.textContent = 'Ler menos';
            } else {
                textoDesc.classList.add('descricao-curta');
                textoDesc.classList.remove('descricao-expandida');
                btnLerMais.textContent = 'Ler mais';
            }
        });
    }

    // --- Lógica do Botão "Ver mais fotos" no Portfólio ---
    const btnVerMaisFotos = document.getElementById('btn-ver-mais-fotos');
    if (btnVerMaisFotos) {
        btnVerMaisFotos.addEventListener('click', () => {
            const hiddenItems = document.querySelectorAll('.portfolio-item-hidden');
            const isExpanded = btnVerMaisFotos.dataset.expanded === 'true';

            // Revela ou oculta as fotos excedentes
            hiddenItems.forEach(item => item.classList.toggle('d-none'));
            btnVerMaisFotos.textContent = isExpanded ? `Ver mais fotos (${hiddenItems.length})` : 'Ver menos';
            btnVerMaisFotos.dataset.expanded = !isExpanded;
        });
    }

    // --- Lógica do Lightbox (Portfólio) ---
    const portfolioImgs = document.querySelectorAll('.portfolio-img');
    const lightbox = document.getElementById('lightbox-modal');
    const lightboxImg = document.querySelector('.lightbox-img');
    const closeBtn = document.querySelector('.lightbox-close');
    const prevBtn = document.querySelector('.lightbox-control.prev');
    const nextBtn = document.querySelector('.lightbox-control.next');
    const indicatorsContainer = document.querySelector('.lightbox-indicators');
    
    let currentImageIndex = 0;
    const imagesSrc = Array.from(portfolioImgs).map(img => img.src);

    const updateLightbox = () => {
        if (imagesSrc.length === 0) return;
        lightboxImg.src = imagesSrc[currentImageIndex];
        
        // Atualiza as bolinhas
        const dots = document.querySelectorAll('.lightbox-dot');
        dots.forEach((dot, i) => {
            dot.classList.toggle('active', i === currentImageIndex);
        });
    };

    if (lightbox && portfolioImgs.length > 0) {
        // Cria as bolinhas dinamicamente
        imagesSrc.forEach((_, i) => {
            const dot = document.createElement('div');
            dot.classList.add('lightbox-dot');
            if (i === 0) dot.classList.add('active');
            dot.addEventListener('click', () => {
                currentImageIndex = i;
                updateLightbox();
            });
            indicatorsContainer.appendChild(dot);
        });

        // Abrir Lightbox
        portfolioImgs.forEach((img, index) => {
            img.addEventListener('click', () => {
                currentImageIndex = index;
                updateLightbox();
                lightbox.classList.add('active');
            });
        });

        // Fechar Lightbox
        closeBtn.addEventListener('click', () => {
            lightbox.classList.remove('active');
        });

        // Fechar clicando fora da imagem
        lightbox.addEventListener('click', (e) => {
            if (e.target === lightbox) {
                lightbox.classList.remove('active');
            }
        });

        // Botões Próximo / Anterior
        nextBtn?.addEventListener('click', () => {
            currentImageIndex = (currentImageIndex + 1) % imagesSrc.length;
            updateLightbox();
        });

        prevBtn?.addEventListener('click', () => {
            currentImageIndex = (currentImageIndex - 1 + imagesSrc.length) % imagesSrc.length;
            updateLightbox();
        });
        
        // Navegação por teclado
        document.addEventListener('keydown', (e) => {
            if (!lightbox.classList.contains('active')) return;
            if (e.key === 'Escape') lightbox.classList.remove('active');
            if (e.key === 'ArrowRight') {
                currentImageIndex = (currentImageIndex + 1) % imagesSrc.length;
                updateLightbox();
            }
            if (e.key === 'ArrowLeft') {
                currentImageIndex = (currentImageIndex - 1 + imagesSrc.length) % imagesSrc.length;
                updateLightbox();
            }
        });
    }

    // --- Lógica de Pré-visualização de Fotos ---
    const inputFotos = document.getElementById('input-fotos-trabalho');
    const previewContainer = document.getElementById('preview-portfolio');

    if (inputFotos) {
        inputFotos.addEventListener('change', function() {
            previewContainer.innerHTML = ''; // Limpa previews anteriores
            
            if (this.files) {
                Array.from(this.files).forEach(file => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'preview-img-item';
                        previewContainer.appendChild(img);
                    }
                    reader.readAsDataURL(file);
                });
            }
        });
    }
});