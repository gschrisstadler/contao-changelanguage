<?php

declare(strict_types=1);

namespace Terminal42\ChangeLanguage\EventListener\Navigation;

use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\FaqCategoryModel;
use Contao\FaqModel;
use Contao\Model;
use Contao\PageModel;
use Terminal42\ChangeLanguage\Event\ChangelanguageNavigationEvent;

/**
 * Translate URL parameters for faq items.
 *
 * @Hook("changelanguageNavigation")
 */
class FaqNavigationListener extends AbstractNavigationListener implements NavigationHandlerInterface
{
    /**
     * @param FaqModel $model
     */
    public function handleNavigation(ChangelanguageNavigationEvent $event, Model $model): void
    {
        $event->getUrlParameterBag()->setUrlAttribute($this->getUrlKey(), $model->alias ?: $model->id);
        $event->getNavigationItem()->setTitle($model->question);
        $event->getNavigationItem()->setPageTitle($model->pageTitle);
    }

    protected function getUrlKey(): string
    {
        return isset($GLOBALS['TL_CONFIG']['useAutoItem']) ? 'items' : 'auto_item';
    }

    protected function findCurrent(): ?FaqModel
    {
        $alias = $this->getAutoItem();

        if ('' === $alias) {
            return null;
        }

        /** @var PageModel $objPage */
        global $objPage;

        if (null === ($calendars = FaqCategoryModel::findBy('jumpTo', $objPage->id))) {
            return null;
        }

        return FaqModel::findPublishedByParentAndIdOrAlias($alias, $calendars->fetchEach('id'));
    }

    /**
     * @param array<string> $columns
     * @param array<string> $values
     * @param array<string, string> $options
     */
    protected function findPublishedBy(array $columns, array $values = [], array $options = []): ?FaqModel
    {
        return FaqModel::findOneBy(
            $this->addPublishedConditions($columns, FaqModel::getTable(), false),
            $values,
            $options,
        );
    }
}
