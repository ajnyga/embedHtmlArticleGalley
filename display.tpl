{**
 * plugins/generic/embedHtmlArticleGalley/display.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Embedded viewing of a HTML galley.
 *}
<!DOCTYPE html>
<html lang="{$currentLocale|replace:"_":"-"}" xml:lang="{$currentLocale|replace:"_":"-"}">
{translate|assign:"pageTitleTranslated" key="article.pageTitle" title=$article->getLocalizedTitle()}
{include file="frontend/components/headerHead.tpl"}
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

	<div id="htmlContainer">
		{$html}
	</div>
	{call_hook name="Templates::Common::Footer::PageFooter"}
</body>
</html>