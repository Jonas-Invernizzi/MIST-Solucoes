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
            
            // Importante: Recalcula o carrossel se a aba de portfólio for aberta
            if (target === 'portfolio') updateCarousel();
        });
    });

    // --- Lógica do Carrossel ---
    const track = document.querySelector('.carousel-track');
    const items = document.querySelectorAll('.carousel-item');
    const nextBtn = document.querySelector('.next');
    const prevBtn = document.querySelector('.prev');

    if (track && items.length > 0) {
        let currentIndex = 0;

        const updateCarousel = () => {
            const width = track.clientWidth; // Pega a largura visível do container
            track.style.transform = `translateX(-${currentIndex * width}px)`;
        };

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