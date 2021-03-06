<?php
/**
* Tab to display recently posted classified ads in the AdsManager component in a Community Builder profile
* Author: Thomas PAPIN (thomas.papin@free.fr)
*/

class AdsManagerTab extends cbTabHandler {

	function getAdsSessionsTab() {
		$cbTabHandler();
	}
			
  function getDisplayTab($tab,$user,$ui) {
	$app = &JFactory::getApplication();
	
  	require_once(JPATH_SITE.'/administrator/components/com_adsmanager/models/adsmanager.php');
	require_once(JPATH_SITE.'/administrator/components/com_adsmanager/models/column.php');
	require_once(JPATH_SITE.'/administrator/components/com_adsmanager/models/category.php');
	require_once(JPATH_SITE.'/administrator/components/com_adsmanager/models/configuration.php');
	require_once(JPATH_SITE.'/administrator/components/com_adsmanager/models/content.php');
	require_once(JPATH_SITE.'/administrator/components/com_adsmanager/models/field.php');
	require_once(JPATH_SITE.'/administrator/components/com_adsmanager/models/position.php');
	require_once(JPATH_SITE.'/administrator/components/com_adsmanager/models/user.php');
	
	require_once(JPATH_SITE.'/components/com_adsmanager/views/list/view.html.php');
	
	if ( file_exists( JPATH_SITE. "/components/com_paidsystem/api.paidsystem.php")) 
	{
		require_once(JPATH_SITE . "/components/com_paidsystem/api.paidsystem.php");
	}

	$uri =& JFactory::getURI();
	$baseurl = JURI::base();
	$document =& JFactory::getDocument();
	$document->addStyleSheet($baseurl.'/components/com_adsmanager/css/adsmanager.css');
	
  	$contentmodel	 = new AdsmanagerModelContent();
	$catmodel		 = new AdsmanagerModelCategory();
	$positionmodel	 = new AdsmanagerModelPosition();
	$columnmodel	 = new AdsmanagerModelColumn();
	$fieldmodel	     = new AdsmanagerModelField();
	$usermodel		 = new AdsmanagerModelUser();
	$adsmanagermodel = new AdsmanagerModelAdsmanager();
	$configurationmodel	= new AdsmanagerModelConfiguration();
	


	$catid = 0;
	
	$config = array();
	$config['template_path'] = JPATH_BASE.'/components/com_adsmanager/views/list/tmpl';
	$view      				 = new AdsmanagerViewList($config);
	
	$uri =& JFactory::getURI();
	$requestURL =& $uri->toString();
	
	$conf = $configurationmodel->getConfiguration();
	
	$filters = array();
	$filters['publish'] =  1;
	
	$filters['user'] = $user->id;
	
  	$tsearch = $app->getUserStateFromRequest('com_adsmanager.front_content.tsearch','tsearch',"");
	if ($tsearch != "")
	{
		$filters['search'] = $tsearch;
		$view->assignRef('tsearch',$tsearch);
	}
	
	$lang = JFactory::getLanguage();
	$lang->load("com_adsmanager");
		
	$category->name = JText::_('ADSMANAGER_LIST_USER_TEXT')." ".$user->name;
	
	$subcats = array();
	$pathlist = array();
	
	$orderfields = $fieldmodel->getOrderFields(0);
	
	$uri =& JFactory::getURI();
	$baseurl = JURI::base();
	$view->assign("baseurl",$baseurl);
	$view->assignRef("baseurl",$baseurl);
	
	$view->assignRef('catid',$catid);
	

	$view->assignRef('listuser',$user->id);
	
	$modeuser = 1;
	$view->assignRef('modeuser',$modeuser);
	
	$itemid = 0;
 	$component =& JComponentHelper::getComponent('com_adsmanager');
	$menus  = &JApplication::getMenu('site', array());
/* 	 $items  = &$menus->getItems('componentid', $component->id); 
	 if ($items)
	{
		$itemid = $items[0]->id;       			
	} */

	$view->assignRef('Itemid',$itemid);
	
	$tsearch = "";
	$view->assignRef('tsearch',$tsearch);
	
	$view->assignRef('orders',$orderfields);
	$view->assignRef('subcats',$subcats);
	$view->assignRef('pathlist',$pathlist);
		
	$limit			  = $app->getUserStateFromRequest('com_adsmanager.front_ads_per_page','limit',$conf->ads_per_page, 'int');
	$limitstart		  = JRequest::getInt("limitstart",0);
	$session =& JFactory::getSession();
	$filters['group']=JRequest::getInt('group', $app->getUserStateFromRequest('com_adsmanager.front_content.group','group',0,'int'));
	$view->assignRef('group',$filters['group']);
	$session->set ('group', $filters['group'], 'adsmanager');
	$order = $app->getUserStateFromRequest('com_adsmanager.front_content.order','order',0,'int');
	$contentmodel->getListOrder($order,$filter_order,$filter_order_Dir);
	$view->assignRef('order',$order);

	$view->assignRef('lists',$lists);
	
	$contents = $contentmodel->getContents($filters,$limitstart, $limit,$filter_order,$filter_order_Dir);
	$total = $contentmodel->getNbContents($filters);
	jimport('joomla.html.pagination');
	$pagination = new JPagination($total, $limitstart, $limit);
	$view->assignRef('pagination',$pagination);
	
	$view->assignRef('list_name',$category->name);
	$view->assignRef('list_img',$category->img);
	$view->assignRef('list_description',$category->description);
	$view->assignRef('contents',$contents);
	
	$mode = $app->getUserStateFromRequest('com_adsmanager.front_content.mode','mode',$conf->display_expand);
	if ($mode == 2)
		$mode = 0;
	$view->assignRef('mode',$mode);
	
	if ($mode == 0) {
		$columns = $columnmodel->getColumns($catid);
		$fcolumns = $fieldmodel->getFieldsbyColumns();
		$view->assignRef('columns',$columns);	
		$view->assignRef('fColumns',$fcolumns);	
	}
	else {
		$positions = $positionmodel->getPositions();
		$fDisplay = $fieldmodel->getFieldsbyPositions();
		$view->assignRef('positions',$positions);	
		$view->assignRef('fDisplay',$fDisplay);	
	}
	
	$fields = $fieldmodel->getFields();
	$view->assignRef('fields',$fields);
	
	$view->assignRef('conf',$conf);
	
	$my = JFactory::getUser();
	$view->assignRef('userid',$my->id);
	
	$view->assignRef('requestURL',$requestURL);
	
	$field_values = $fieldmodel->getFieldValues();
	
	$plugins = $fieldmodel->getPlugins();
	$field = new JHTMLAdsmanagerField($conf,$field_values,$mode,$plugins,$view->get("Itemid"),$view->get("baseurl"),null);
	$view->assignRef('field',$field);
	
	$general = new JHTMLAdsmanagerGeneral($catid,$conf->comprofiler,$user,$view->get("Itemid"));
	$view->assignRef('general',$general);

	$return = $view->loadTemplate(null);
	
	$path = JPATH_ADMINISTRATOR.'/../libraries/joomla/database/table';
	JTable::addIncludePath($path);
	
	return $return;
  }
}
