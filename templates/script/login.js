document.addEventListener('DOMContentLoaded', () => {
  // --- LÓGICA DO MODAL (Esqueci Senha) ---
  const linkEsqueci = document.getElementById('link-esqueci-senha');
  const modal = document.getElementById('modal-senha');
  const formRedefinirEmail = document.getElementById('form-redefinir-email'); // ID alterado
  const btnCancelar = document.getElementById('btn-cancelar');

  if (linkEsqueci && modal) {
    linkEsqueci.addEventListener('click', (e) => {
      e.preventDefault();
      modal.classList.add('ativo');
    });

    btnCancelar.addEventListener('click', () => {
      modal.classList.remove('ativo');
      if (formRedefinirEmail) formRedefinirEmail.reset(); // Limpa o formulário ao cancelar
    });

    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        modal.classList.remove('ativo');
        if (formRedefinirEmail) formRedefinirEmail.reset(); // Limpa o formulário ao clicar fora
      }
    });
  }

  // --- LÓGICA DE TROCA DE ABAS (Cadastro -> Verificação) ---
  const secaoRegistro = document.getElementById('secao-registro');
  const secaoVerificacao = document.getElementById('secao-verificacao');
  const btnProximo = document.getElementById('btn-proximo-registro'); // Crie este ID no botão
  const btnVoltar = document.getElementById('btn-voltar-registro');   // Crie este ID no botão

  // Função para avançar para a verificação de código
  if (btnProximo && secaoRegistro && secaoVerificacao) {
    btnProximo.addEventListener('click', () => {
      secaoRegistro.style.display = 'none';
      secaoVerificacao.style.display = 'block';
    });
  }

  // Função para voltar para os dados de cadastro
  if (btnVoltar) {
    btnVoltar.addEventListener('click', () => {
      secaoVerificacao.style.display = 'none';
      secaoRegistro.style.display = 'flex';
    });
  }

  // --- LÓGICA DO MODAL DE VERIFICAÇÃO ---
const btnAbrirVerificacao = document.getElementById('btn-abrir-verificacao');
const btnFecharVerificacao = document.getElementById('btn-fechar-verificacao');
const modalVerificacao = document.getElementById('modal-verificacao');
const emailInput = document.getElementById('email-input');
const exibirEmail = document.getElementById('exibir-email');

if (btnAbrirVerificacao && modalVerificacao) {
  btnAbrirVerificacao.addEventListener('click', () => {
    // 1. Pega o valor do e-mail que o usuário digitou
    const valorEmail = emailInput.value;
    
    // 2. Só abre o modal se o e-mail não estiver vazio (validação básica)
    if (valorEmail.includes('@')) {
      exibirEmail.textContent = valorEmail; // Coloca o e-mail no texto do modal
      modalVerificacao.classList.add('ativo'); // Abre o modal (usa a mesma classe do outro)
    } else {
      alert("Por favor, insira um e-mail válido.");
    }
  });

  // Fecha o modal se clicar em "Corrigir E-mail"
  btnFecharVerificacao.addEventListener('click', () => {
    modalVerificacao.classList.remove('ativo');
  });
}
});