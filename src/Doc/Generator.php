<?php

declare(strict_types=1);

namespace Dof\Framework\Doc;

use Dof\Framework\RouteManager;
use Dof\Framework\Doc\UI\GitBook;

final class Generator
{
    const SUPPORT_UI = [
        'gitbook' => GitBook::class,
    ];
    const TEMPLATE_DIR = 'templates';

    private static function support(string $ui)
    {
        $_ui = self::SUPPORT_UI[$ui] ?? null;
        if (! $_ui) {
            exception('DocUiNotSupport', compact('ui'));
        }

        return new $_ui;
    }

    /**
     * Build Http Docs with given $ui and save to $save
     *
     * @param string $ui: The docs ui to use
     * @param string $save: The docs path to save
     */
    public static function buildHttp(string $ui, string $save, string $lang = 'zh-CN')
    {
        $docs = RouteManager::getDocs();

        $templates = ospath(dirname(dirname(dirname(__FILE__))), self::TEMPLATE_DIR);

        self::support($ui)
            ->setTemplates($templates)
            ->setLanguage($lang)
            ->setOutput($save)
            ->setData($docs)
            ->buildHttp();
    }
}
