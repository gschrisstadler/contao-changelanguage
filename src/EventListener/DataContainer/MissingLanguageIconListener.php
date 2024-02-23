<?php

declare(strict_types=1);

namespace Terminal42\ChangeLanguage\EventListener\DataContainer;

use Composer\InstalledVersions;
use Contao\ArticleModel;
use Contao\Backend;
use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Config;
use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\Date;
use Contao\FaqCategoryModel;
use Contao\FaqModel;
use Contao\Input;
use Contao\NewsArchiveModel;
use Contao\NewsModel;
use Contao\PageModel;
use Contao\StringUtil;
use Symfony\Component\Security\Core\Security;
use Terminal42\ChangeLanguage\Helper\LabelCallback;

/**
 * @Hook("loadDataContainer")
 */
class MissingLanguageIconListener
{
    private static $callbacks;

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * Override core labels to show missing language information.
     */
    public function __invoke(string $table): void
    {
        $callbacks = self::getCallbacks();

        if (\array_key_exists($table, $callbacks)) {
            LabelCallback::createAndRegister(
                $table,
                fn (array $args, $previousResult) => $this->{$callbacks[$table]}($args, $previousResult),
            );
        }
    }

    /**
     * Adds missing translation warning to page tree.
     */
    private function onPageLabel(array $args, $previousResult = null): string
    {
        [$row, $label] = $args;

        if ($previousResult) {
            $label = $previousResult;
        }

        if ('root' === $row['type'] || 'folder' === $row['type'] || 'page' !== Input::get('do')) {
            return $label;
        }

        if (
            ($page = PageModel::findWithDetails($row['id'])) !== null
            && ($root = PageModel::findByPk($page->rootId)) !== null
            && (!$root->fallback || $root->languageRoot > 0)
            && (!$page->languageMain || null === ($mainPage = PageModel::findByPk($page->languageMain)))
        ) {
            return $this->generateLabelWithWarning($label);
        }

        $user = $this->security->getUser();

        if (
            isset($mainPage)
            && $mainPage instanceof PageModel
            && $user instanceof BackendUser
            && \is_array($user->pageLanguageLabels)
            && \in_array($page->rootId, $user->pageLanguageLabels, false)
        ) {
            return sprintf(
                '%s <span style="color:#999;padding-left:3px">(<a href="%s" title="%s" style="color:#999">%s</a>)</span>',
                $label,
                Backend::addToUrl('pn='.$mainPage->id),
                StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['selectNode']),
                $mainPage->title,
            );
        }

        return $label;
    }

    /**
     * Adds missing translation warning to article tree.
     */
    private function onArticleLabel(array $args, $previousResult = null): string
    {
        [$row, $label] = $args;

        if ($previousResult) {
            $label = $previousResult;
        }

        if (
            $row['showTeaser']
            && ($page = PageModel::findWithDetails($row['pid'])) !== null
            && ($root = PageModel::findByPk($page->rootId)) !== null
            && (!$root->fallback || $root->languageRoot > 0)
            && $page->languageMain > 0 && null !== PageModel::findByPk($page->languageMain)
            && (!$row['languageMain'] || null === ArticleModel::findByPk($row['languageMain']))
        ) {
            return $this->generateLabelWithWarning($label);
        }

        return $label;
    }

    /**
     * Generate missing translation warning for news child records.
     */
    private function onNewsChildRecords(array $args, $previousResult = null): string
    {
        $row = $args[0];
        $label = (string) $previousResult;

        if (empty($label)) {
            $label = '<div class="tl_content_left">'.$row['headline'].' <span style="color:#999;padding-left:3px">['.Date::parse(Config::get('datimFormat'), $row['date']).']</span></div>';
        }

        $archive = NewsArchiveModel::findByPk($row['pid']);

        if (
            null !== $archive
            && $archive->master
            && (!$row['languageMain'] || null === NewsModel::findByPk($row['languageMain']))
        ) {
            return $this->generateLabelWithWarning($label);
        }

        return $label;
    }

    /**
     * Generate missing translation warning for calendar events child records.
     */
    private function onCalendarEventChildRecords(array $args, $previousResult = null): string
    {
        $row = $args[0];
        $label = (string) $previousResult;

        $calendar = CalendarModel::findByPk($row['pid']);

        if (
            null !== $calendar
            && $calendar->master
            && (!$row['languageMain'] || null === CalendarEventsModel::findByPk($row['languageMain']))
        ) {
            return $this->generateLabelWithWarning($label);
        }

        return $label;
    }

    /**
     * Generate missing translation warning for faq child records.
     */
    private function onFaqChildRecords(array $args, $previousResult = null): string
    {
        $row = $args[0];
        $label = (string) $previousResult;

        $category = FaqCategoryModel::findByPk($row['pid']);

        if (
            null !== $category
            && $category->master
            && (!$row['languageMain'] || null === FaqModel::findByPk($row['languageMain']))
        ) {
            return preg_replace(
                '#</div>#',
                $this->generateLabelWithWarning('', 'position:absolute;top:6px').'</div>',
                $label,
                1,
            );
        }

        return $label;
    }

    /**
     * @param string $label
     * @param string $imgStyle
     *
     * @return string
     */
    private function generateLabelWithWarning($label, $imgStyle = '')
    {
        return $label.sprintf(
            '<span style="padding-left:3px"><img src="%s" alt="%s" title="%s" style="%s"></span>',
            'bundles/terminal42changelanguage/language-warning.png',
            $GLOBALS['TL_LANG']['MSC']['noMainLanguage'],
            $GLOBALS['TL_LANG']['MSC']['noMainLanguage'],
            $imgStyle,
        );
    }

    private static function getCallbacks(): array
    {
        if (null !== self::$callbacks) {
            return self::$callbacks;
        }

        $callbacks = [
            'tl_page' => 'onPageLabel',
            'tl_article' => 'onArticleLabel',
        ];

        if (InstalledVersions::isInstalled('contao/news-bundle')) {
            $callbacks['tl_news'] = 'onNewsChildRecords';
        }

        if (InstalledVersions::isInstalled('contao/calendar-bundle')) {
            $callbacks['tl_calendar_events'] = 'onCalendarEventChildRecords';
        }

        if (InstalledVersions::isInstalled('contao/faq-bundle')) {
            $callbacks['tl_faq'] = 'onFaqChildRecords';
        }

        return self::$callbacks = $callbacks;
    }
}
