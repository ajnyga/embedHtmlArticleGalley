<?php

/**
 * @file plugins/generic/embedHtmlArticleGalley/EmbedHtmlArticleGalleyPlugin.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EmbedHtmlArticleGalleyPlugin
 * @ingroup plugins_generic_embedHtmlArticleGalley
 *
 * @brief Class for EmbedHtmlArticleGalley plugin
 */

namespace APP\plugins\generic\embedHtmlArticleGalley;

use DOMDocument;
use APP\plugins\generic\htmlArticleGalley\HtmlArticleGalleyPlugin;
use APP\publication\Publication;
use APP\template\TemplateManager;
use PKP\config\Config;
use PKP\plugins\Hook;

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
   *
   * @param string $hookName
   * @param array $args
   */
  public function articleViewCallback($hookName, $args)
  {
    $request = & $args[0];
    $issue = & $args[1];
    $galley = & $args[2];
    $article = & $args[3];

    if (!$galley) {
      return false;
    }

    $submissionFile = $galley->getFile();
    /** @var ?Publication */
    if ($submissionFile->getData('mimetype') === 'text/html') {

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
            foreach ($links as $l) {
              if ($l->getAttribute("rel") == "stylesheet") {
                $templateMgr->addHeader('embedStylesheet'. $count .'', '<link rel="stylesheet" type="text/css" href="' . $l->getAttribute("href") . '">');
                $count++;
              }
            }
          }

          if ($head->getElementsByTagName('script')->length != 0) {
            $scripts = $head->getElementsByTagName("script");
            $count = 0;
            foreach($scripts as $script) {
              if (stristr($script->getAttribute("src"), '.js')) {
                $templateMgr->addHeader('embedJs'. $count .'', '<script type="text/javascript" src="' . $script->getAttribute("src") . '"></script>');
                $count++;
              }
            }
          }
        }
      } else {
        $body = $doc->savehtml();
      }

      Hook::call('HtmlArticleGalleyPlugin::articleDownloadFinished', array(&$returner));
      $templateMgr->assign([
        'issue' => $issue,
        'article' => $article,
        'html' => $body
      ]);
      $templateMgr->display($this->getTemplateResource('display.tpl'));

      return true;
    }

    return false;
  }
}
