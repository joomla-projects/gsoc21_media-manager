<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_joomlaupdate
 *
 * @copyright   (C) 2021 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Session\Session;

/** @var HtmlView $this */

/** @var WebAssetManager $wa */
$wa = $this->document->getWebAssetManager();
$wa->useScript('core')
	->useScript('com_joomlaupdate.default')
	->useScript('bootstrap.popover');

$uploadLink = 'index.php?option=com_joomlaupdate&view=upload';

$displayData = [
	'textPrefix' => 'COM_JOOMLAUPDATE_UPDATE',
	'title'      => Text::sprintf('COM_JOOMLAUPDATE_UPDATE_EMPTYSTATE_TITLE', $this->escape($this->updateInfo['latest'])),
	'content'    => Text::sprintf($this->langKey, $this->updateSourceKey),
	'formURL'    => 'index.php?option=com_joomlaupdate&view=joomlaupdate',
	'helpURL'    => 'https://docs.joomla.org/Special:MyLanguage/Updating_from_an_existing_version',
	'icon'       => 'icon-loop joomlaupdate',
	'createURL'  => '#'
];

if (isset($this->updateInfo['object']) && isset($this->updateInfo['object']->get('infourl')->_data)) :
	$displayData['content'] .= '<br>' . HTMLHelper::_('link',
		$this->updateInfo['object']->get('infourl')->_data,
		Text::_('COM_JOOMLAUPDATE_VIEW_DEFAULT_INFOURL'),
		[
			'target' => '_blank',
			'rel'    => 'noopener noreferrer',
			'title'  => isset($this->updateInfo['object']->get('infourl')->title) ? Text::sprintf('JBROWSERTARGET_NEW_TITLE', $this->updateInfo['object']->get('infourl')->title) : ''
		]
	);
endif;

// Confirm backup and check
$displayData['content'] .= '<div class="form-check d-flex justify-content-center">
		<input class="form-check-input me-2" type="checkbox" value="" id="joomlaupdate-confirm-backup">
		<label class="form-check-label" for="joomlaupdate-confirm-backup">
		' . Text::_('COM_JOOMLAUPDATE_UPDATE_CONFIRM_BACKUP') . '
		</label>
	</div>';

if (Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_joomlaupdate')) :
	$displayData['formAppend'] = '<div class="text-center">' . HTMLHelper::_('link', $uploadLink, Text::_('COM_JOOMLAUPDATE_EMPTYSTATE_APPEND')) . '</div>';
endif;

echo '<div id="joomlaupdate-wrapper">';

echo LayoutHelper::render('joomla.content.emptystate', $displayData);

echo '</div>';
