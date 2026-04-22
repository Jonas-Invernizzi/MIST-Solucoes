document.addEventListener('DOMContentLoaded', () => {
  const linkEsqueci = document.getElementById('link-esqueci-senha');
  const modal = document.getElementById('modal-senha');
  const formRedefinir = document.getElementById('form-redefinir');
  const btnCancelar = document.getElementById('btn-cancelar');

  if (linkEsqueci && modal) {
    linkEsqueci.addEventListener('click', (e) => {
      e.preventDefault();
      modal.classList.add('ativo');
    });

    btnCancelar.addEventListener('click', () => {
      modal.classList.remove('ativo');
    });

    modal.addEventListener('click', (e) => {
      if (e.target === modal) modal.classList.remove('ativo');
    });
  }

  if (formRedefinir) {
    formRedefinir.addEventListener('submit', (e) => {
      e.preventDefault();
      modal.classList.remove('ativo');
      setTimeout(() => formRedefinir.reset(), 400);
    });
  }

const btnAbrirVerificacao = document.getElementById('btn-abrir-verificacao');
const btnFecharVerificacao = document.getElementById('btn-fechar-verificacao');
const modalVerificacao = document.getElementById('modal-verificacao');
const emailInput = document.getElementById('email-input');
const exibirEmail = document.getElementById('exibir-email');

if (btnAbrirVerificacao && modalVerificacao) {
  btnAbrirVerificacao.addEventListener('click', () => {
    const valorEmail = emailInput.value;
    
    if (valorEmail.includes('@')) {
      exibirEmail.textContent = valorEmail; 
      modalVerificacao.classList.add('ativo');
    } else {
      alert("Por favor, insira um e-mail válido.");
    }
  });

  btnFecharVerificacao.addEventListener('click', () => {
    modalVerificacao.classList.remove('ativo');
  });
}
});


