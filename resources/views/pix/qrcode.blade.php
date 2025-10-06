<!DOCTYPE html>
<html>

<head>
    <title>Pix Gerado</title>
</head>

<body>
    <h1>Cobrança Pix Criada</h1>

    <p>Status: {{ $status }}</p>

    @if($qrCode)
    <p>QR Code:</p>
    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($qrCode) }}" alt="QR Code">
    <p>Chave/Link Pix: {{ $qrCode }}</p>
    @else
    <p>QR Code não disponível.</p>
    @endif

    <a href="{{ route('pix.form') }}">Criar nova cobrança</a>
</body>

</html>