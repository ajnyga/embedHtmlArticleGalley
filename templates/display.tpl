{**
 * plugins/generic/embedHtmlArticleGalley/display.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Embedded viewing of a HTML galley.
 *}
<!DOCTYPE html>
<html lang="{$currentLocale|replace:"_":"-"}" xml:lang="{$currentLocale|replace:"_":"-"}">
{capture assign="pageTitleTranslated"}{translate key="article.pageTitle" title=$article->getLocalizedTitle()|escape}{/capture}
{include file="frontend/components/header.tpl" pageTitleTranslated=$article->getLocalizedTitle()|escape}
<body class="pkp_page_{$requestedPage|escape} pkp_op_{$requestedOp|escape}">

{* Header wrapper *}
<header class="header_view">

    <a href="{url page="article" op="view" path=$article->getBestArticleId()}" class="return">
			<span class="pkp_screen_reader">
				{translate key="article.return"}
			</span>
    </a>

    <a href="{url page="article" op="view" path=$article->getBestArticleId()}" class="title">
        {$article->getLocalizedTitle()|escape}
    </a>
</header>

{* Atla Change 2024/02/22 - display breadcrumbs *}
{if $section}
    {include file="frontend/components/breadcrumbs_article.tpl" currentTitle=$section->getLocalizedTitle()}
{else}
    {include file="frontend/components/breadcrumbs_article.tpl" currentTitleKey="common.publication"}
{/if}

<div id="htmlContainer">
    {$html}

    {* UZH CHANGE OJS-67 2019/03/08/mb display Licensing info *}
    {* Licensing info *}
    {if $copyright || $licenseUrl}
        <div class="item copyright">
            {if $licenseUrl}
                {if $ccLicenseBadge}
                    {if $copyrightHolder}
                        <p>{translate key="submission.copyrightStatement" copyrightHolder=$copyrightHolder copyrightYear=$copyrightYear}</p>
                    {/if}
                    {$ccLicenseBadge}
                {else}
                    <a href="{$licenseUrl|escape}" class="copyright">
                        {if $copyrightHolder}
                            {translate key="submission.copyrightStatement" copyrightHolder=$copyrightHolder copyrightYear=$copyrightYear}
                        {else}
                            {translate key="submission.license"}
                        {/if}
                    </a>
                {/if}
            {/if}
        </div>
    {/if}
    {* END UZH CHANGE OJS-67 *}
</div>

{include file="frontend/components/footer.tpl"}
</body>
</html>