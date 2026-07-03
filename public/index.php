<?php

declare(strict_types=1);

use ModulaPdfService\Bootstrap;
use ModulaPdfService\PdfRenderer;

require dirname(__DIR__) . '/vendor/autoload.php';

$state = Bootstrap::init(dirname(__DIR__));
$renderer = new PdfRenderer();

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$apiKey = (string) ($state['apiKey'] ?? '');
$playgroundEnabled = (bool) ($state['playgroundEnabled'] ?? false);

if ($uri === '/health') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'fontFamily' => $state['fontFamily'],
        'apiProtected' => $apiKey !== '',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($uri === '/api/render' && $method === 'POST') {
    if ($apiKey !== '') {
        $receivedApiKey = trim((string) ($_SERVER['HTTP_X_MODULA_PDF_KEY'] ?? ''));
        if ($receivedApiKey === '' || !hash_equals($apiKey, $receivedApiKey)) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => true,
                'message' => 'API key invalide.',
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
    }

    $rawBody = file_get_contents('php://input') ?: '';
    $payload = json_decode($rawBody, true);

    if (!is_array($payload)) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => true,
            'message' => 'Payload JSON invalide.',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    $pdf = $renderer->render($payload, (string) $state['fontFamily']);

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="preview-invoice.pdf"');
    echo $pdf;
    exit;
}

if (!$playgroundEnabled) {
    http_response_code($uri === '/' ? 200 : 404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'service' => 'modula-pdf-service',
        'ok' => true,
        'playground' => false,
        'availableEndpoints' => [
            'GET /health',
            'POST /api/render',
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$samplePayload = [
    'title' => 'Facture de test tc-lib-pdf',
    'filename' => 'invoice-preview.pdf',
    'documentTitle' => 'Facture',
    'documentNumber' => 'FAC-2026-0001',
    'issuedAt' => date('d/m/Y'),
    'statusLabel' => 'Payee',
    'seller' => [
        'name' => 'Modula CMS',
        'email' => 'contact@example.com',
        'address' => '12 rue du Test',
        'city' => '31000 Toulouse',
    ],
    'customer' => [
        'name' => 'Client exemple',
        'email' => 'client@example.com',
        'phone' => '06 00 00 00 00',
        'address' => '5 avenue de la Commande',
    ],
    'items' => [
        [
            'name' => 'Produit exemple premium',
            'reference' => 'PROD-001',
            'quantity' => 1,
            'unitPrice' => 120,
            'total' => 120,
        ],
        [
            'name' => 'Accessoire complementaire',
            'reference' => 'ACC-002',
            'quantity' => 2,
            'unitPrice' => 15,
            'total' => 30,
        ],
    ],
    'totals' => [
        'subtotal' => 150,
        'vat' => 25,
        'grandTotal' => 175,
    ],
    'notes' => 'Apercu local du rendu PDF pour integration future dans le registry.',
];

$sampleJson = json_encode($samplePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<!doctype html>
<html lang="fr">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Modula PDF Service</title>
    <style>
      body {
        margin: 0;
        font-family: Arial, sans-serif;
        background: #f3f4f6;
        color: #111827;
      }
      .shell {
        max-width: 1100px;
        margin: 40px auto;
        padding: 0 20px;
      }
      .panel {
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08);
      }
      h1 {
        margin: 0 0 8px;
      }
      .meta {
        margin-bottom: 24px;
        color: #475569;
      }
      textarea {
        width: 100%;
        min-height: 420px;
        font: 13px/1.5 Consolas, monospace;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 14px;
        box-sizing: border-box;
      }
      .actions {
        display: flex;
        gap: 12px;
        align-items: center;
        margin-top: 16px;
      }
      button, a.button {
        appearance: none;
        border: 0;
        border-radius: 10px;
        background: #1d4ed8;
        color: #fff;
        padding: 12px 18px;
        cursor: pointer;
        text-decoration: none;
        font-weight: 600;
      }
      .status {
        color: #475569;
      }
      code {
        background: #e2e8f0;
        border-radius: 6px;
        padding: 2px 6px;
      }
    </style>
  </head>
  <body>
    <div class="shell">
      <div class="panel">
        <h1>Modula PDF Service</h1>
        <div class="meta">
          Test local avec <code>tc-lib-pdf</code> uniquement.
          Healthcheck: <a href="/health" target="_blank">/health</a>.
          Police active: <code><?= htmlspecialchars((string) $state['fontFamily'], ENT_QUOTES, 'UTF-8') ?></code>
        </div>

        <textarea id="payload"><?= htmlspecialchars((string) $sampleJson, ENT_QUOTES, 'UTF-8') ?></textarea>

        <div class="actions">
          <button id="previewButton" type="button">Generer le PDF</button>
          <span class="status" id="status">Pret.</span>
        </div>
      </div>
    </div>

    <script>
      const payloadField = document.getElementById('payload');
      const statusNode = document.getElementById('status');
      const previewButton = document.getElementById('previewButton');

      previewButton.addEventListener('click', async () => {
        statusNode.textContent = 'Generation en cours...';

        try {
          const response = await fetch('/api/render', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: payloadField.value
          });

          if (!response.ok) {
            const errorText = await response.text();
            throw new Error(errorText || 'Erreur serveur');
          }

          const blob = await response.blob();
          const url = URL.createObjectURL(blob);
          window.open(url, '_blank', 'noopener,noreferrer');
          statusNode.textContent = 'PDF genere.';
        } catch (error) {
          statusNode.textContent = 'Erreur: ' + (error instanceof Error ? error.message : String(error));
        }
      });
    </script>
  </body>
</html>
