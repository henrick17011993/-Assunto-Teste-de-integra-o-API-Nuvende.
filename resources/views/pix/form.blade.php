<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Criar Cobrança Pix</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        background: #f0f2f5;
        color: #333;
        margin: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
    }

    .container {
        background: #fff;
        padding: 2rem;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        width: 400px;
    }

    h1 {
        color: #0077cc;
        text-align: center;
        margin-bottom: 1.5rem;
    }

    label {
        font-weight: bold;
    }

    input {
        width: 100%;
        padding: 10px;
        margin-top: 5px;
        margin-bottom: 15px;
        border: 1px solid #ccc;
        border-radius: 6px;
    }

    button {
        background-color: #0077cc;
        color: white;
        border: none;
        padding: 12px 0;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
        width: 100%;
        font-size: 16px;
        transition: background 0.3s;
    }

    button:hover {
        background-color: #005fa3;
    }

    .alert {
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 15px;
        word-break: break-word;
    }

    .alert-error {
        background: #ffe5e5;
        border: 1px solid #ff7b7b;
        color: #b30000;
    }

    .alert-success {
        background: #e5ffe5;
        border: 1px solid #66cc66;
        color: #006600;
    }

    pre {
        background: #f5f5f5;
        padding: 10px;
        border-radius: 6px;
        overflow-x: auto;
    }

    #message {
        margin-top: 20px;
    }

    #clearButton {
        background: #ccc;
        color: #333;
        margin-top: 10px;
        font-weight: normal;
        width: auto;
        padding: 6px 12px;
    }

    #clearButton:hover {
        background: #aaa;
    }
    </style>
</head>

<body>
    <div class="container">
        <h1>Criar Cobrança Pix</h1>

        <form id="pixForm">
            @csrf
            <label for="amount">Valor (R$):</label>
            <input type="number" id="amount" step="0.01" name="amount" value="100.00" required>

            <label for="payer_name">Nome do pagador (opcional):</label>
            <input type="text" id="payer_name" name="payer_name" placeholder="Ex: João da Silva">

            <label for="cpf">CPF do pagador (opcional):</label>
            <input type="text" id="cpf" name="cpf" placeholder="Somente números">

            <button type="submit">💸 Gerar Cobrança Pix</button>
        </form>

        <div id="message"></div>
        <button id="clearButton">🗑 Limpar mensagem</button>
    </div>

    <script>
    const messageDiv = document.getElementById('message');
    const clearButton = document.getElementById('clearButton');

    document.getElementById('pixForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const form = e.target;
        const formData = new FormData(form);

        const payload = {
            amount: formData.get('amount'),
            payer_name: formData.get('payer_name'),
            cpf: formData.get('cpf'),
        };

        messageDiv.innerHTML = '<p>⏳ Enviando solicitação...</p>';

        try {
            const response = await fetch("{{ route('pix.create') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': form.querySelector('[name=_token]').value,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();
            messageDiv.innerHTML = '';

            if (result.success) {
                messageDiv.innerHTML = `
                        <div class="alert alert-success">
                            ✅ <strong>Cobrança Pix criada com sucesso!</strong><br>
                            <small>Valor: R$ ${payload.amount}</small><br>
                            <pre>${JSON.stringify(result.data, null, 2)}</pre>
                        </div>
                    `;
            } else {
                let detalhes = '';
                if (result.details) {
                    detalhes = Object.entries(result.details).map(([key, value]) => {
                        if (typeof value === 'object') value = JSON.stringify(value, null, 2);
                        return `<b>${key}:</b> ${value}<br>`;
                    }).join('');
                }

                messageDiv.innerHTML = `
                        <div class="alert alert-error">
                            ❌ <strong>Falha ao criar cobrança Pix</strong><br>
                            ${detalhes}
                        </div>
                    `;
            }
        } catch (error) {
            console.error(error);
            messageDiv.innerHTML = `
                    <div class="alert alert-error">
                        ❌ <strong>Erro de conexão com o servidor.</strong><br>
                        Tente novamente em alguns instantes.
                    </div>
                `;
        }
    });

    clearButton.addEventListener('click', () => {
        messageDiv.innerHTML = '';
    });
    </script>
</body>

</html>