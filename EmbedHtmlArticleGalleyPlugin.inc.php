<?php

/**
 * @file plugins/generic/embedHtmlArticleGalley/EmbedHtmlArticleGalleyPlugin.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EmbedHtmlArticleGalleyPlugin
 * @ingroup plugins_generic_embedHtmlArticleGalley
 *
 * @brief Class for EmbedHtmlArticleGalley plugin
 */

import('plugins.generic.htmlArticleGalley.HtmlArticleGalleyPlugin');

class EmbedHtmlArticleGalleyPlugin extends HtmlArticleGalleyPlugin {
	/**
	 * @see Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		if (parent::register($category, $path, $mainContextId)) {
			return true;
		}
		return false;
	}

	/**
	 * Get the display name of this plugin.
	 * @return String
	 */
	function getDisplayName() {
		return __('plugins.generic.embedHtmlArticleGalley.displayName');
	}

	/**
	 * Get a description of the plugin.
	 */
	function getDescription() {
		return __('plugins.generic.embedHtmlArticleGalley.description');
	}

	/**
	 * Present the article wrapper page.
	 * @param string $hookName
	 * @param array $args
	 */
	function articleViewCallback($hookName, $args) {
		$request =& $args[0];
		$issue =& $args[1];
		$galley =& $args[2];
		$article =& $args[3];

		if ($galley && $galley->getFileType() == 'text/html') {
			$fileId = $galley->getFileId();
			if (!HookRegistry::call('HtmlArticleGalleyPlugin::articleDownload', array($article,  &$galley, &$fileId))) {
				$templateMgr = TemplateManager::getManager($request);
				$html = $this->_getHTMLContents($request, $galley);
				$doc = new DOMDocument();
				libxml_use_internal_errors(true);

				if (Config::getVar('i18n', 'client_charset') === "utf-8")
					$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
				else
					$doc->loadHTML($html);

				if ($doc->getElementsByTagName('body')->length != 0) {

					$bodyElement = $doc->getElementsByTagName('body')->item(0);
					$body = "";
					foreach ($bodyElement->childNodes as $childNode) {
					  $body .= $doc->saveHTML($childNode);
					}				

					if ($doc->getElementsByTagName('head')->length != 0) {
						$head = $doc->getElementsByTagName('head')->item(0);
						if ($head->getElementsByTagName('link')->length != 0) {
							$links = $head->getElementsByTagName("link");
							$count = 0;
							foreach($links as $l) {
							    if($l->getAttribute("rel") == "stylesheet") {
							        $templateMgr->addHeader('embedStylesheet'. $count .'', '<link rel="stylesheet" type="text/css" href="' . $l->getAttribute("href") . '">');
							        $count++;
							    }
							}
						}
						if ($head->getElementsByTagName('script')->length != 0) {
							$scripts = $head->getElementsByTagName("script");
							$count = 0;
							foreach($scripts as $script) {
				    				if(stristr($script->getAttribute("src"), '.js')) {
				        				$templateMgr->addHeader('embedJs'. $count .'', '<script type="text/javascript" src="' . $script->getAttribute("src") . '"></script>');
				        				$count++;
								}
				    			}
						}
					}

				} else {
					$body = $doc->savehtml(); 
				}

				$returner = true;
				HookRegistry::call('HtmlArticleGalleyPlugin::articleDownloadFinished', array(&$returner));	
				$templateMgr->assign(array(
					'issue' => $issue,
					'article' => $article,
					'html' => $body,
				));
				$templateMgr->display($this->getTemplateResource('display.tpl'));
				return true;
			}
		}

		return false;
	}

}

?>
