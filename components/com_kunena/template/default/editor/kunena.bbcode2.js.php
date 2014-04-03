<?php
/**
 * @version $Id: kunena.bbcode.js.php 4336 2011-01-31 06:05:12Z severdia $
 * Kunena Component
 * @package Kunena
 *
 * @Copyright (C) 2008 - 2011 Kunena Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link http://www.kunena.org
 **/

defined( '_JEXEC' ) or die();

$kunena_config = KunenaFactory::getConfig ();
require_once (KPATH_SITE . DS . 'lib' .DS. 'kunena.poll.class.php');
$kunena_poll =& CKunenaPolls::getInstance();
$document =& JFactory::getDocument();

ob_start();

//
// function kPreviewHelper (elementId)
//
// Helper function for to perform JSON request for preview
//
?>
function kPreviewHelper()
{
	if (_previewActive == true){
		previewRequest = new Request.JSON({url: "<?php echo CKunenaLink::GetJsonURL('preview');?>",
				  							onSuccess: function(response){
			var __message = $("kbbcode-preview");
			if (__message) {
				__message.set("html", response.preview);
			}
			}}).post({body: $("kbbcode-message").get("value")
		});
	}
}

<?php
// Now we instanciate the class in an object and implement all the buttons and functions.
?>
window.addEvent('domready', function() {
<?php
//creating a kbbcode object
?>
kbbcode = new kbbcode('kbbcode-message', 'kbbcode-toolbar', {
				dispatchChangeEvent: true,
				changeEventDelay: 1000,
				interceptTab: true
			});

kbbcode.addFunction('Bold', function() {
	this.wrapSelection('[b]', '[/b]', false);
}, {'id': 'kbbcode-bold-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_BOLD');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_BOLD');?>'});

kbbcode.addFunction('Italic', function() {
	this.wrapSelection('[i]', '[/i]', false);
}, {'id': 'kbbcode-italic-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_ITALIC');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_ITALIC');?>'});

kbbcode.addFunction('Underline', function() {
	this.wrapSelection('[u]', '[/u]', false);
}, {'id': 'kbbcode-underline-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_UNDERL');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_UNDERL');?>'});

kbbcode.addFunction('Strike', function() {
	this.wrapSelection('[strike]', '[/strike]', false);
}, {'id': 'kbbcode-strike-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_STRIKE');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_STRIKE');?>'});

kbbcode.addFunction('Sub', function() {
	this.wrapSelection('[sub]', '[/sub]', false);
}, {'id': 'kbbcode-sub-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_SUB');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_SUB');?>'});

kbbcode.addFunction('Sup', function() {
	this.wrapSelection('[sup]', '[/sup]', false);
}, {'id': 'kbbcode-sup-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_SUP');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_SUP');?>'});

kbbcode.addFunction('Size', function() {
	kToggleOrSwap("kbbcode-size-options");
}, {'id': 'kbbcode-size-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_FONTSIZE');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_FONTSIZE');?>'});

kbbcode.addFunction('Color', function() {
	kToggleOrSwap("kbbcode-colorpalette");
}, {'id': 'kbbcode-color-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_COLOR');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_COLOR');?>'});

kbbcode.addFunction('#', function() {
}, {'id': 'kbbcode-separator1'});

<?php
//adding a "List" button that will create a new list if nothing is selected
//or make each line of the selection into a list item if some text is selected...
 ?>
kbbcode.addFunction("uList", function() {
	selection = this.getSelection();
	if (selection == "") {
		this.wrapSelection("\n[ul]\n  [li]", "[/li]\n  [li][/li]\n[/ul]", false);
	}
	else {
		this.processEachLine(function(line) {
			newline = "  [li]" + line + "[/li]";
			return newline;
		}, false);
		this.insert("[ul]\n", "before", false);
		this.insert("\n[/ul]\n", "after", true); //now isLast is set to true, because it is the last one!
	}
}, {'id': 'kbbcode-ulist-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_ULIST');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_ULIST');?>'});

kbbcode.addFunction("oList", function() {
	selection = this.getSelection();
	if (selection == "") {
		this.wrapSelection("\n[ol]\n  [li]", "[/li]\n  [li][/li]\n[/ol]", false);
	}
	else {
		this.processEachLine(function(line) {
			newline = "  [li]" + line + "[/li]";
			return newline;
		}, false);
		this.insert("[ol]\n", "before", false);
		this.insert("\n[/ol]\n", "after", true); //now isLast is set to true, because it is the last one!
	}
}, {'id': 'kbbcode-olist-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_OLIST');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_OLIST');?>'});

kbbcode.addFunction('List', function() {
	this.wrapSelection('  [li]', '[/li]', false);
}, {'id': 'kbbcode-list-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_LIST');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_LIST');?>'});

kbbcode.addFunction('Left', function() {
	this.wrapSelection('[left]', '[/left]', false);
}, {'id': 'kbbcode-left-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_LEFT');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_LEFT');?>'});

kbbcode.addFunction('Center', function() {
	this.wrapSelection('[center]', '[/center]', false);
}, {'id': 'kbbcode-center-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_CENTER');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_CENTER');?>'});

kbbcode.addFunction('Right', function() {
	this.wrapSelection('[right]', '[/right]', false);
}, {'id': 'kbbcode-right-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_RIGHT');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_RIGHT');?>'});

kbbcode.addFunction('#', function() {
}, {'id': 'kbbcode-separator2'});

kbbcode.addFunction('Quote', function() {
	this.wrapSelection('[quote]', '[/quote]', false);
}, {'id': 'kbbcode-quote-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_QUOTE');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_QUOTE');?>'});

<?php
if ($kunena_config->highlightcode) { ?>
kbbcode.addFunction('Code', function() {
	kToggleOrSwap("kbbcode-code-options");
}, {'id': 'kbbcode-code-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_CODE');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_CODE');?>'});
<?php
} else {
?>
kbbcode.addFunction('Code', function() {
	this.wrapSelection('[code]', '[/code]', false);
}, {'id': 'kbbcode-code-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_CODE');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_CODE');?>'});
<?php
}
?>

kbbcode.addFunction("Table", function() {
	selection = this.getSelection();
	if (selection == "") {
		this.wrapSelection("\n[table]\n  [tr]\n    [td]", "[/td]\n    [td][/td]\n  [/tr]\n  [tr]\n    [td][/td]\n    [td][/td]\n  [/tr]\n[/table]", false);
	}
	else {
		this.processEachLine(function(line) {
			newline = "  [tr][td]" + line + "[/td][/tr]";
			return newline;
		}, false);
		this.insert("[table]\n", "before", false);
		this.insert("\n[/table]\n", "after", true); //now isLast is set to true, because it is the last one!
	}
}, {'id': 'kbbcode-table-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_TABLE');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_TABLE');?>'});

<?php
if ($kunena_config->showspoilertag) {
?>
kbbcode.addFunction('Spoiler', function() {
	this.wrapSelection('[spoiler]', '[/spoiler]', false);
}, {'id': 'kbbcode-spoiler-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_SPOILER');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_SPOILER');?>'});
<?php
}
?>

kbbcode.addFunction('Hide', function() {
	this.wrapSelection('[hide]', '[/hide]', false);
}, {'id': 'kbbcode-hide-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_HIDE');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_HIDE');?>'});

kbbcode.addFunction('#', function() {
}, {'id': 'kbbcode-separator3'});

kbbcode.addFunction('Image', function() {
	kToggleOrSwap("kbbcode-image-options");
}, {'id': 'kbbcode-image-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_IMAGELINK');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_IMAGELINK');?>'});

kbbcode.addFunction('Link', function() {
	sel = this.getSelection();
	if (sel != "") {
		$('kbbcode-link_text').set('value', sel);
	}
	kToggleOrSwap("kbbcode-link-options");
}, {'id': 'kbbcode-link-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_LINK');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_LINK');?>'});

kbbcode.addFunction('#', function() {
}, {'id': 'kbbcode-separator4'});
<?php
/*
kbbcode.addFunction('Gallery', function() {
	kToggleOrSwap("kbbcode-gallery-options");
}, {'id': 'kbbcode-gallery-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_GALLERY');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_GALLERY');?>',
	'onmouseover' : '$("helpbox").set("value", "<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_GALLERY');?>")'});
*/
?>

<?php

if ($kunena_config->showebaytag) {
?>
kbbcode.addFunction('eBay', function() {
	this.wrapSelection('[ebay]', '[/ebay]', false);
}, {'id': 'kbbcode-ebay-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_EBAY');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_EBAY');?>'});
<?php
}
?>

<?php
if ($kunena_config->showvideotag) {
?>
kbbcode.addFunction('Video', function() {
	kToggleOrSwap("kbbcode-video-options");
}, {'id': 'kbbcode-video-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_VIDEO');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_VIDEO');?>'});
<?php
}
?>

kbbcode.addFunction('Map', function() {
	this.wrapSelection('[map]', '[/map]', false);
}, {'id': 'kbbcode-map-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_MAP');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_MAP');?>'});

<?php if (1==0) { // disable for now TODO: make safe - dont allow public to access modules?>
kbbcode.addFunction('Module', function() {
	this.wrapSelection('[module]', '[/module]', false);
}, {'id': 'kbbcode-module-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_MODULE');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_MODULE');?>'});
<?php } // end disable ?>

kbbcode.addFunction('#', function() {
}, {'id': 'kbbcode-separator6'});

<?php if ($this->my->id != 0) { ?>
kbbcode.addFunction('PreviewBottom', function() {
	kToggleOrSwapPreview("kbbcode-preview-bottom");
}, {'id': 'kbbcode-previewbottom-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_PREVIEWBOTTOM');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_PREVIEWBOTTOM');?>'});

kbbcode.addFunction('PreviewRight', function() {
	kToggleOrSwapPreview("kbbcode-preview-right");
}, {'id': 'kbbcode-previewright-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_PREVIEWRIGHT');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_PREVIEWRIGHT');?>'});

kbbcode.addFunction('#', function() {
}, {'id': 'kbbcode-separator7'});

<?php } ?>

kbbcode.addFunction('Help', function() {
	window.open('http://docs.kunena.org/index.php/bbcode');
}, {'id': 'kbbcode-help-button',
	'title': '<?php echo JText::_('COM_KUNENA_EDITOR_HELP');?>',
	'alt': '<?php echo JText::_('COM_KUNENA_EDITOR_HELPLINE_HELP');?>'});


<?php
// Add the click behaviors for our bbcode options
?>
	var color = $$("table.kbbcode-colortable td");
	if (color) {
		color.addEvent("click", function(){
			var bg = this.getStyle( "background-color" );
			kbbcode.wrapSelection('[color='+ bg +']', '[/color]', false);
			kToggleOrSwap("kbbcode-colorpalette");
		});
	}
	var size = $$("div#kbbcode-size-options span");
	if (size) {
		size.addEvent("click", function(){
			var tag = this.get( "title" );
			kbbcode.wrapSelection(tag , '[/size]', false);
			kToggleOrSwap("kbbcode-size-options");
		});
	}

	bindAttachments();
	newAttachment();
	// Fianlly apply some screwy IE7 and IE8 fixes to the html...
	IEcompatibility();
});


<?php
$script = ob_get_contents();
ob_end_clean();

CKunenaTools::addScript(KUNENA_DIRECTURL . 'template/default/js/editor-min.js');
$document->addScriptDeclaration( "// <![CDATA[
{$script}
// ]]>");
