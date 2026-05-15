document.addEventListener('DOMContentLoaded', () => {
    /**
     * Aplica a máscara de telefone (XX) XXXXX-XXXX ou (XX) XXXX-XXXX.
     * @param {HTMLInputElement} input O elemento input do telefone.
     */
    function applyPhoneMask(input) {
        input.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, ''); // Remove tudo que não é dígito
            let formattedValue = '';

            if (value.length > 0) {
                formattedValue = '(' + value.substring(0, 2); // DDD
                if (value.length > 2) {
                    formattedValue += ') ';
                    if (value.length > 7) { // Para números de 9 dígitos (ex: 9XXXX-XXXX)
                        formattedValue += value.substring(2, 7) + '-' + value.substring(7, 11);
                    } else if (value.length > 6) { // Para números de 8 dígitos (ex: XXXX-XXXX)
                        formattedValue += value.substring(2, 6) + '-' + value.substring(6, 10);
                    } else { // Menos de 8 dígitos
                        formattedValue += value.substring(2, value.length);
                    }
                }
            }
            e.target.value = formattedValue;
        });
    }

    /**
     * Aplica a máscara de CPF XXX.XXX.XXX-XX.
     * @param {HTMLInputElement} input O elemento input do CPF.
     */
    function applyCpfMask(input) {
        input.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, '').substring(0, 11); // Remove tudo que não é dígito e limita a 11
            let formattedValue = '';

            formattedValue = value.replace(/(\d{3})(\d)/, '$1.$2');
            formattedValue = formattedValue.replace(/(\d{3})(\d)/, '$1.$2');
            formattedValue = formattedValue.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            
            e.target.value = formattedValue;
        });
    }

    // Aplica as máscaras aos inputs correspondentes
    document.querySelectorAll('input[type="tel"]').forEach(applyPhoneMask);
    document.querySelectorAll('input[name="cpf"]').forEach(applyCpfMask);
});