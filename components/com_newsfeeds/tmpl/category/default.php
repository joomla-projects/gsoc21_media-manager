<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_newsfeeds
 *
 * @copyright   (C) 2006 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Helper\MediaHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;

$pageClass = $this->params->get('pageclass_sfx');
$htag    = $this->params->get('show_page_heading') ? 'h2' : 'h1';

$img = HTMLHelper::cleanImageURL($this->category->getParams()->get('image'));
$img_sizes = $this->category->getParams()->get('image_sizes');
$img_size_options = $this->category->getParams()->get('image_size_options');
$img_method = $this->category->getParams()->get('image_method');

?>
<div class="com-newsfeeds-category newsfeed-category">
	<?php if ($this->params->get('show_page_heading')) : ?>
		<h1>
			<?php echo $this->escape($this->params->get('page_heading')); ?>
		</h1>
	<?php endif; ?>
	<?php if ($this->params->get('show_category_title', 1)) : ?>
		<<?php echo $htag; ?>>
			<?php echo HTMLHelper::_('content.prepare', $this->category->title, '', 'com_newsfeeds.category.title'); ?>
		</<?php echo $htag; ?>>
	<?php endif; ?>
	<?php if ($this->params->get('show_tags', 1) && !empty($this->category->tags->itemTags)) : ?>
		<?php $this->category->tagLayout = new FileLayout('joomla.content.tags'); ?>
		<?php echo $this->category->tagLayout->render($this->category->tags->itemTags); ?>
	<?php endif; ?>
	<?php if ($this->params->get('show_description', 1) || $this->params->def('show_description_image', 1)) : ?>
		<div class="com-newsfeeds-category__description category-desc">
			<?php if ($this->params->get('show_description_image') && $this->category->getParams()->get('image')) : ?>
				<img src="<?php echo $this->category->getParams()->get('image'); ?>"
					<?php echo sprintf('srcset="%1s" sizes="%2s"', MediaHelper::createFormSrcset($img->url, $img_sizes, $img_size_options, $img_method), MediaHelper::generateSizes($img->url)); ?>
				>
			<?php endif; ?>
			<?php if ($this->params->get('show_description') && $this->category->description) : ?>
				<?php echo HTMLHelper::_('content.prepare', $this->category->description, '', 'com_newsfeeds.category'); ?>
			<?php endif; ?>
			<div class="clr"></div>
		</div>
	<?php endif; ?>
	<?php echo $this->loadTemplate('items'); ?>
	<?php if ($this->maxLevel != 0 && !empty($this->children[$this->category->id])) : ?>
		<div class="com-newsfeeds-category__children cat-children">
			<h3>
				<?php echo Text::_('JGLOBAL_SUBCATEGORIES'); ?>
			</h3>
			<?php echo $this->loadTemplate('children'); ?>
		</div>
	<?php endif; ?>
</div>
