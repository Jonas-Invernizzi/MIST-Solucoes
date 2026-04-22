document.addEventListener('DOMContentLoaded', () => {
  const linkEsqueci = document.getElementById('link-esqueci-senha');
  const modalSenha = document.getElementById('modal-senha');
  const formRedefinir = document.getElementById('form-redefinir');
  const btnCancelar = document.getElementById('btn-cancelar');
  const inputNovoEmail = document.getElementById('novo-email');

  const modalVerificacao = document.getElementById('modal-verificacao');
  const spanExibirEmail = document.getElementById('exibir-email');
  const btnCorrigirEmail = document.getElementById('btn-fechar-verificacao');

  if (linkEsqueci && modalSenha) {
    linkEsqueci.addEventListener('click', (e) => {
      e.preventDefault();
      modalSenha.classList.add('ativo');
    });
  }

  if (btnCancelar && modalSenha) {
    btnCancelar.addEventListener('click', () => {
      modalSenha.classList.remove('ativo');
    });
  }

  window.addEventListener('click', (e) => {
    if (e.target === modalSenha) modalSenha.classList.remove('ativo');
    if (e.target === modalVerificacao) modalVerificacao.classList.remove('ativo');
  });

  if (formRedefinir) {
    formRedefinir.addEventListener('submit', (e) => {
      e.preventDefault(); 
      if (inputNovoEmail && spanExibirEmail) {
        spanExibirEmail.textContent = inputNovoEmail.value;
      }

      modalSenha.classList.remove('ativo');

      if (modalVerificacao) {
        modalVerificacao.classList.add('ativo');
      }

      setTimeout(() => formRedefinir.reset(), 400);
    });
  }

  if (btnCorrigirEmail && modalVerificacao && modalSenha) {
    btnCorrigirEmail.addEventListener('click', () => {
      modalVerificacao.classList.remove('ativo');
      modalSenha.classList.add('ativo');
    });
  }
});