<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Serves Swagger UI and the OpenAPI specification.
 */
final class DocsController
{
    private string $docsDir;

    public function __construct(?string $docsDir = null)
    {
        $this->docsDir = $docsDir ?? dirname(__DIR__, 2) . '/docs';
    }

    public function handle(string $path): array|string
    {
        return match ($path) {
            '/docs', '/docs/' => $this->swaggerHtml(),
            '/docs/openapi.yaml', '/docs/openapi.yml' => $this->openApiYaml(),
            default => $this->notFound(),
        };
    }

    private function swaggerHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Pardis API — Swagger</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
  <style>
    body { margin: 0; background: #fafafa; }
    .topbar { display: none; }
  </style>
</head>
<body>
  <div id="swagger-ui"></div>
  <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
  <script>
    window.onload = () => {
      SwaggerUIBundle({
        url: '/docs/openapi.yaml',
        dom_id: '#swagger-ui',
        deepLinking: true,
        presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
        layout: 'BaseLayout',
        persistAuthorization: true,
        tryItOutEnabled: true,
      });
    };
  </script>
</body>
</html>
HTML;
    }

    private function openApiYaml(): array
    {
        $file = $this->docsDir . '/openapi.yaml';
        if (!is_file($file)) {
            return $this->notFound();
        }

        return [
            'content_type' => 'application/yaml',
            'data'         => (string) file_get_contents($file),
            'status'       => 200,
        ];
    }

    private function notFound(): array
    {
        return [
            'success' => false,
            'status'  => 404,
            'message' => 'Documentation not found.',
            'data'    => null,
            'error'   => true,
        ];
    }
}
