<?php

declare(strict_types=1);

namespace Terminal42\ChangeLanguage\EventListener\BackendView;

use Composer\InstalledVersions;
use Contao\Controller;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\Input;
use Contao\Model;
use Contao\PageModel;
use Contao\System;
use League\Uri\Uri;
use League\Uri\UriModifier;

class ParentChildViewListener extends AbstractViewListener
{
    /**
     * @var Model
     */
    private $current = false;

    protected function isSupported()
    {
        return $this->getTable() === Input::get('table') && (
            ('news' === Input::get('do') && InstalledVersions::isInstalled('contao/news-bundle'))
            || ('calendar' === Input::get('do') && InstalledVersions::isInstalled('contao/calendar-bundle'))
            || ('faq' === Input::get('do') && InstalledVersions::isInstalled('contao/faq-bundle'))
        );
    }

    protected function getCurrentPage()
    {
        if (false === $this->current) {
            /** @var string|Model $class */
            $class = $this->getModelClass();

            if (!class_exists($class)) {
                return null;
            }

            if ('paste' === Input::get('act') || ('edit' === Input::get('act') && 'tl_content' === $this->getTable())) {
                $t = $class::getTable();
                $this->current = $class::findOneBy(["$t.id=(SELECT pid FROM ".$this->getTable().' WHERE id=?)'], [$this->dataContainer->id]);
            } else {
                $this->current = $class::findByPk($this->dataContainer->id);
            }
        }

        if (null === $this->current) {
            return null;
        }

        $pageId = $this->current->pid ? $this->current->getRelated('pid')->jumpTo : $this->current->jumpTo;

        return PageModel::findWithDetails($pageId);
    }

    protected function getAvailableLanguages(PageModel $page)
    {
        $options = [];
        $masterRoot = $this->pageFinder->findMasterRootForPage($page);
        $parent = $this->hasParent() ? 'languageMain' : 'master';
        $id = (int) ($page->rootId === $masterRoot->id ? $this->current->id : $this->current->{$parent});

        if (0 === $id) {
            return [];
        }

        foreach ($this->pageFinder->findAssociatedForPage($page, true, null, false) as $associated) {
            $associated->loadDetails();
            $model = $this->findRelatedForPageAndId($associated, $id);

            if (null !== $model) {
                $options[$model->id] = $this->getLanguageLabel($associated->language);
            }
        }

        return $options;
    }

    protected function doSwitchView($id): void
    {
        $uri = Uri::createFromString(System::getContainer()->get('request_stack')->getCurrentRequest()->getUri());

        if ('edit' === Input::get('act') && 'tl_content' !== $this->getTable()) {
            $uri = UriModifier::removeParams($uri, 'switchLanguage');
        } else {
            $uri = UriModifier::removeParams($uri, 'switchLanguage', 'act', 'mode');
        }

        $uri = UriModifier::mergeQuery($uri, 'id='.$id);

        throw new RedirectResponseException((string) $uri);
    }

    /**
     * Finds related item for a given page.
     *
     * @param int $id
     *
     * @return Model|null
     */
    private function findRelatedForPageAndId(PageModel $page, $id)
    {
        /** @var Model $class */
        $class = $this->getModelClass();
        $table = $class::getTable();

        if ($this->hasParent()) {
            $ptable = $GLOBALS['TL_DCA'][$table]['config']['ptable'];
            $columns = [
                "$table.pid IN (SELECT id FROM $ptable WHERE jumpTo=?)",
                "$table.id!=?",
                "($table.id=? OR $table.languageMain=?)",
            ];
        } else {
            $columns = [
                "$table.jumpTo=?",
                "$table.id!=?",
                "($table.id=? OR $table.master=?)",
            ];
        }

        return $class::findOneBy($columns, [
            $page->id,
            $this->current->id,
            $id,
            $id,
        ]);
    }

    private function getModelClass(): string
    {
        Controller::loadDataContainer($this->getTable());

        if ('edit' === Input::get('act') && 'tl_content' !== $this->getTable()) {
            return Model::getClassFromTable($this->getTable());
        }

        return Model::getClassFromTable($GLOBALS['TL_DCA'][$this->getTable()]['config']['ptable']);
    }

    private function hasParent()
    {
        /** @var Model $class */
        $class = $this->getModelClass();
        $table = $class::getTable();

        Controller::loadDataContainer($table);

        return !empty($GLOBALS['TL_DCA'][$table]['config']['ptable']);
    }
}
