<?php

declare(strict_types=1);

namespace Terminal42\ChangeLanguage\EventListener\DataContainer;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\DataContainer;
use Contao\Input;
use Contao\PageModel;

/**
 * @Hook("loadDataContainer")
 */
class PageInitializationListener
{
    /**
     * Register our own callbacks.
     */
    public function __invoke(string $table): void
    {
        if ('tl_page' !== $table) {
            return;
        }

        $GLOBALS['TL_DCA']['tl_page']['config']['onload_callback'][] = function (DataContainer $dc): void {
            $this->onLoad($dc);
        };
    }

    /**
     * Load page data container configuration depending on current mode.
     */
    public function onLoad(DataContainer $dc): void
    {
        if ('page' !== Input::get('do')) {
            return;
        }

        switch (Input::get('act')) {
            case 'edit':
                $this->handleEditMode($dc);
                break;

            case 'editAll':
                $this->handleEditAllMode();
                break;
        }
    }

    private function handleEditMode(DataContainer $dc): void
    {
        $page = PageModel::findById($dc->id);

        if (null === $page) {
            return;
        }

        if ('root' === $page->type) {
            if ($page->fallback) {
                $this->addRootLanguageFields();
            }

            return;
        }

        $root = PageModel::findById($page->loadDetails()->rootId);
        $addLanguageMain = true;

        if (
            !$root
            || ($root->fallback && (!$root->languageRoot || null === PageModel::findById($root->languageRoot)))
        ) {
            $addLanguageMain = false;
        }

        $this->addRegularLanguageFields($page->type, $addLanguageMain);
    }

    private function handleEditAllMode(): void
    {
        $this->addRootLanguageFields();
        $this->addRegularLanguageFields(
            array_diff(
                array_keys($GLOBALS['TL_DCA']['tl_page']['palettes']),
                ['__selector__', 'root', 'rootfallback', 'folder'],
            ),
        );
    }

    private function addRootLanguageFields(): void
    {
        $hasLegacyRouting = isset($GLOBALS['TL_DCA']['tl_page']['fields']['disableLanguageRedirect']);

        $pm = PaletteManipulator::create()
            ->addField('languageRoot', $hasLegacyRouting ? 'language' : 'fallback')
            ->applyToPalette('root', 'tl_page')
        ;

        if (isset($GLOBALS['TL_DCA']['tl_page']['palettes']['rootfallback'])) {
            $pm->applyToPalette('rootfallback', 'tl_page');
        }
    }

    /**
     * @param array|string $palettes
     */
    private function addRegularLanguageFields($palettes, bool $addLanguageMain = true): void
    {
        $pm = PaletteManipulator::create()
            ->addLegend('language_legend', 'meta_legend', PaletteManipulator::POSITION_BEFORE, true)
            ->addField('languageQuery', 'language_legend', PaletteManipulator::POSITION_APPEND)
        ;

        if ($addLanguageMain) {
            $pm->addField('languageMain', 'language_legend', PaletteManipulator::POSITION_PREPEND);
        }

        foreach ((array) $palettes as $palette) {
            if (!isset($GLOBALS['TL_DCA']['tl_page']['palettes'][$palette])) {
                continue;
            }

            $pm->applyToPalette($palette, 'tl_page');
        }
    }
}
