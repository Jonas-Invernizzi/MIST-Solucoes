document.addEventListener('DOMContentLoaded', () => {
    // --- Variáveis Globais do Carrossel (acessíveis pelas abas) ---
    const track = document.querySelector('.carousel-track');
    const items = document.querySelectorAll('.carousel-item');
    let currentIndex = 0;
    let updateCarousel = () => {}; // Função vazia por padrão

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
            
            // Importante: Recalcula o carrossel se a aba de portfólio for aberta
            if (target === 'portfolio') updateCarousel();
        });
    });

    // --- Lógica do Carrossel ---
    const nextBtn = document.querySelector('.next');
    const prevBtn = document.querySelector('.prev');
    const indicatorsContainer = document.querySelector('.carousel-indicators');

    if (track && items.length > 0) {
        updateCarousel = () => {
            // Usa o clientWidth do container para maior precisão se o item ainda não estiver renderizado
            const width = track.parentElement.clientWidth; 
            if (width === 0) return; // Evita cálculos se ainda estiver invisível
            
            track.style.transform = `translateX(-${currentIndex * width}px)`;

            // Atualiza o estado das bolinhas
            const dots = document.querySelectorAll('.dot');
            dots.forEach((dot, i) => {
                dot.classList.toggle('active', i === currentIndex);
            });
        };

        // Cria as bolinhas dinamicamente baseado no número de imagens
        if (indicatorsContainer) {
            items.forEach((_, i) => {
                const dot = document.createElement('div');
                dot.classList.add('dot');
                if (i === 0) dot.classList.add('active');
                dot.addEventListener('click', () => {
                    currentIndex = i;
                    updateCarousel();
                });
                indicatorsContainer.appendChild(dot);
            });
        }

        nextBtn?.addEventListener('click', () => {
            currentIndex = (currentIndex + 1) % items.length;
            updateCarousel();
        });

        prevBtn?.addEventListener('click', () => {
            currentIndex = (currentIndex - 1 + items.length) % items.length;
            updateCarousel();
        });

        // Reajusta em caso de redimensionamento da janela
        window.addEventListener('resize', updateCarousel);

        // Executa uma vez no início caso a aba de portfólio já comece aberta
        if (document.getElementById('portfolio')?.classList.contains('active')) {
            setTimeout(updateCarousel, 100);
        }
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