<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKit\Controller;

use OpenColibri\Starter\Themes\ThemePreview;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The thumbnails of the storefront themes, shipped with opencolibri/starter-themes, for the theme picker.
 */
final class ThemePreviewController
{
    #[Route('/themes/{theme}/preview-{scheme}.webp', name: 'opencolibri_sales_kit_theme_preview', requirements: ['theme' => '[a-z0-9_]+', 'scheme' => 'light|dark'], methods: ['GET'])]
    public function __invoke(string $theme, string $scheme): BinaryFileResponse
    {
        $path = ThemePreview::path($theme, $scheme) ?? throw new NotFoundHttpException(\sprintf('The "%s" theme has no preview.', $theme));

        $response = new BinaryFileResponse($path, headers: ['Content-Type' => 'image/webp'], autoEtag: true, autoLastModified: true);
        $response->setPrivate();
        $response->setMaxAge(86400);

        return $response;
    }
}
