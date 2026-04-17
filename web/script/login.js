    document.addEventListener('DOMContentLoaded', () => {
      const linkEsqueci = document.getElementById('link-esqueci-senha');
      const modal = document.getElementById('modal-senha');
      const formRedefinir = document.getElementById('form-redefinir');
      const btnCancelar = document.getElementById('btn-cancelar');

      // Abre o modal ao clicar em "Esqueceu sua senha?"
      linkEsqueci.addEventListener('click', (e) => {
        e.preventDefault(); // Evita que a página pule para o topo
        modal.classList.add('ativo');
      });

      // Fecha o modal ao clicar em "Salvar" (fazendo o submit do form)
      formRedefinir.addEventListener('submit', (e) => {
        e.preventDefault(); // Evita que a página recarregue de verdade
        // (Aqui entraria a lógica de salvar a senha no banco de dados)
        
        // Remove a classe para disparar o fade out
        modal.classList.remove('ativo');
        
        // Limpa os campos após fechar
        setTimeout(() => {
          formRedefinir.reset();
        }, 400); // Aguarda o fim da animação
      });

      // Fecha o modal ao clicar em Cancelar
      btnCancelar.addEventListener('click', () => {
        modal.classList.remove('ativo');
      });

      // Fecha o modal se o usuário clicar na área borrada (fora do card)
      modal.addEventListener('click', (e) => {
        if (e.target === modal) {
          modal.classList.remove('ativo');
        }
      });
    });