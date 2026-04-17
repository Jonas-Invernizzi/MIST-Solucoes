    document.addEventListener('DOMContentLoaded', () => {
      const linkEsqueci = document.getElementById('link-esqueci-senha');
      const modal = document.getElementById('modal-senha');
      const formRedefinir = document.getElementById('form-redefinir');
      const btnCancelar = document.getElementById('btn-cancelar');

      linkEsqueci.addEventListener('click', (e) => {
        e.preventDefault(); 
        modal.classList.add('ativo');
      });

      formRedefinir.addEventListener('submit', (e) => {
        e.preventDefault(); 

        modal.classList.remove('ativo');
        
        setTimeout(() => {
          formRedefinir.reset();
        }, 400);
      });

      btnCancelar.addEventListener('click', () => {
        modal.classList.remove('ativo');
      });
      modal.addEventListener('click', (e) => {
        if (e.target === modal) {
          modal.classList.remove('ativo');
        }
      });
    });