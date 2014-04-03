<?php
/**
* Joomla/Mambo Community Builder
* @version $Id: controller.tab.php 1764 2012-02-19 22:35:07Z beat $
* @package Community Builder
* @subpackage admin.comprofiler.php : tab controller
* @author Beat
* @copyright (C) Beat, www.joomlapolis.com
* @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU/GPL version 2
*/

// ensure this file is being included by a parent file
if ( ! ( defined( '_VALID_CB' ) || defined( '_JEXEC' ) || defined( '_VALID_MOS' ) ) ) { die( 'Direct Access to this location is not allowed.' ); }

class CBController_tab {
	function _importNeeded() {
		cbimport( 'cb.tabs' );

		if ( class_exists( 'JFactory' ) ) {	// Joomla 1.5 : for string WARNREG_EMAIL_INUSE used in error js popup.
			$lang			=&	JFactory::getLanguage();
			$lang->load( "com_users" );
		}
		cbimport( 'cb.params' );
		
	}
	function _importNeededSave() {
		// backend only:
		cbimport( 'cb.adminfilesystem' );
		cbimport( 'cb.imgtoolbox' );
	}

	/**
	* @param  string   $sql        SQL with ordering As value and 'name field' AS text
	* @param  int      $chop       The length of the truncated headline
	* @param  boolean  $translate  translate to CB language
	* @return array                of makeOption
	* @access private
	*/
	function _cbGetOrderingList( $sql, $chop = 30, $translate = true ) {
		global $_CB_database;
	
		$order				=	array();
		$_CB_database->setQuery( $sql );
		$orders				= $_CB_database->loadObjectList();
		if ( $_CB_database->getErrorNum() ) {
			echo $_CB_database->stderr();
			return false;
		}
		if ( count( $orders ) == 0 ) {
			$order[]	=	moscomprofilerHTML::makeOption( 1, CBTxt::T('first') );
			return $order;
		}
		$order[]			=	moscomprofilerHTML::makeOption( 0, '0 ' . CBTxt::T('first') );
		for ( $i=0, $n = count( $orders ); $i < $n; $i++ ) {
			if ( $translate ) {
				$text		=	getLangDefinition( $orders[$i]->text );
			} else {
				$text		=	$orders[$i]->text;
			}
			if ( cbIsoUtf_strlen( $text ) > $chop ) {
	        	$text		=	cbIsoUtf_substr( $text, 0, $chop ) . '...';
	        }
	
			$order[]		=	moscomprofilerHTML::makeOption( $orders[$i]->value, $orders[$i]->value . ' (' . $text . ')' );
		}
		if ( isset( $orders[$i - 1] ) ) {
			$order[]		=	moscomprofilerHTML::makeOption( $orders[$i - 1]->value + 1, ( $orders[$i - 1]->value + 1 ) . ' ' . CBTxt::T('last') );
		}
		return $order;
	}
	
	function editTab( $tid='0', $option='com_comprofiler', $task='editTab' ) {
		global $_CB_database, $_CB_framework, $_PLUGINS;

		$this->_importNeeded();

		$canEditState			=	CBuser::getMyInstance()->authoriseAction( 'core.edit.state' );

		$row = new moscomprofilerTabs( $_CB_database );
		if ( $tid ) {
			// load the row from the db table
			$row->load( (int) $tid );
			// Check if user is a super user:
			if ( ! $_CB_framework->acl->amIaSuperAdmin() ) {
				// Check if user belongs to useraccessgroupid:
				if ( ! in_array( $row->useraccessgroupid, $_CB_framework->acl->get_groups_below_me( null, true ) ) ) {
					echo "<script type=\"text/javascript\"> alert('" . addslashes( CBTxt::T('Unauthorized Access') ) . "'); window.history.go(-1);</script>\n";
					exit;
				}
				// Check if user belongs to viewaccesslevel:
				if ( ! in_array( $row->viewaccesslevel, CBuser::getMyInstance()->getAuthorisedViewLevelsIds( false ) ) ) {
					echo "<script type=\"text/javascript\"> alert('" . addslashes( CBTxt::T('Unauthorized Access') ) . "'); window.history.go(-1);</script>\n";
					exit;
				}
			}
		}

	 	$lists = array();
		if($row->sys=='2') $lists['enabled'] = "Yes";
		else $lists['enabled'] = moscomprofilerHTML::yesnoSelectList( 'enabled', 'class="inputbox" size="1"' . ( $canEditState ? '' : ' disabled="disabled"' ), ( $row->enabled !== null ? $row->enabled : 1 ) );
	
		/*
		-------------------------
		!          head         !
		!-----------------------!
		!      !        !       !
		! left ! middle ! right !
		!      !        !       !
		!-----------------------!
		!                       !
		!        tabmain        !
		!                       !
		!-----------------------!
		!        underall       !
		-------------------------
		!      !        !       !
		! L1C1 ! L1C2   ! L1C3  !   L1C1...C9
		!      !        !       !
		!-----------------------!
		!      !        !       !
		! L2C1 ! L2C4   ! L2C8  !   ...
		!      !        !       !
		!-----------------------!
		!                       !
		!        L4C7           !
		!                       !
		!-----------------------!
		!          !            !
		!   L8C3   !    L8C4    !   ...L9C9
		!          !            !
		!-----------------------!
	    ! + not_on_profile_1..9
		*/
		$position = array();
		$position[] = moscomprofilerHTML::makeOption( 'cb_head', _UE_POS_CB_HEAD );
		$position[] = moscomprofilerHTML::makeOption( 'cb_left', _UE_POS_CB_LEFT );
		$position[] = moscomprofilerHTML::makeOption( 'cb_middle', _UE_POS_CB_MIDDLE );
		$position[] = moscomprofilerHTML::makeOption( 'cb_right', _UE_POS_CB_RIGHT );
		$position[] = moscomprofilerHTML::makeOption( 'cb_tabmain', _UE_POS_CB_MAIN );
		$position[] = moscomprofilerHTML::makeOption( 'cb_underall', _UE_POS_CB_BOTTOM );
		for ( $i = 1 ; $i <= 9; $i++ ) {
			for ( $j = 1 ; $j <= 9; $j++ ) {
				$position[] = moscomprofilerHTML::makeOption( 'L'.$i.'C'.$j, CBTxt::T('Line') . ' ' . $i . ' ' . CBTxt::T('Column') . ' ' . $j );
			}
		}
		for ( $i = 1 ; $i <= 9; $i++ ) {
			$position[] = moscomprofilerHTML::makeOption( 'not_on_profile_'.$i, CBTxt::T('Not displayed on profile') . ' ' . $i );
		}
	
		if ( ! $row->position ) {
			$row->position		=	'cb_tabmain';
		}
		$lists['position'] = moscomprofilerHTML::selectList( $position, 'position', 'class="inputbox" size="1"', 'value', 'text', $row->position, 2 );
	
		$displaytype = array();
		$displaytype[] = moscomprofilerHTML::makeOption( 'tab', _UE_DISPLAY_TAB );
		$displaytype[] = moscomprofilerHTML::makeOption( 'div', _UE_DISPLAY_DIV );
		$displaytype[] = moscomprofilerHTML::makeOption( 'rounddiv', _UE_DISPLAY_ROUNDED_DIV );
		$displaytype[] = moscomprofilerHTML::makeOption( 'html', _UE_DISPLAY_HTML );
		$displaytype[] = moscomprofilerHTML::makeOption( 'overlib', _UE_DISPLAY_OVERLIB );
		$displaytype[] = moscomprofilerHTML::makeOption( 'overlibfix', _UE_DISPLAY_OVERLIBFIX );
		$displaytype[] = moscomprofilerHTML::makeOption( 'overlibsticky', _UE_DISPLAY_OVERLIBSTICKY );
		if ( ! $row->displaytype ) {
			$row->displaytype	=	'tab';
		}
		$lists['displaytype'] = moscomprofilerHTML::selectList( $displaytype, 'displaytype', 'class="inputbox" size="1"', 'value', 'text', $row->displaytype, 2 );
	
		if ($tid) {
			if ( $row->ordering > -10000 && $row->ordering < 10000 ) {
				// build the html select list for ordering
				$query = "SELECT ordering AS value, title AS text"
				. "\n FROM #__comprofiler_tabs"
				. "\n WHERE position='" . $_CB_database->getEscaped( $row->position ) . "'"
				. "\n AND enabled > 0"
				. "\n AND ordering > -10000"
				. "\n AND ordering < 10000"
				. "\n ORDER BY ordering"
				;
				$order = $this->_cbGetOrderingList( $query );
				$lists['ordering'] = moscomprofilerHTML::selectList( $order, 'ordering', 'class="inputbox" size="1"' . ( $canEditState ? '' : ' disabled="disabled"' ), 'value', 'text', intval( $row->ordering ), 2 );
			} else {
				$lists['ordering'] = '<input type="hidden" name="ordering" value="'. $row->ordering .'" />' . CBTxt::T('This plugin cannot be reordered') ;
			}
		} else {
			$row->ordering 				= 999;
			$row->ordering_register		= 10;
			$row->published 			= 1;
			$row->description 			= '';
			$row->useraccessgroupid		= -2;
			$row->viewaccesslevel		= 1;
			$lists['ordering']	= '<input type="hidden" name="ordering" value="'. $row->ordering
								.'" />'. CBTxt::T('New items default to the last place. Ordering can be changed after this item is saved.');
		}

		$lists['ordering_register']	=	'<input type="text" name="ordering_register" class="inputbox"' . ( $canEditState ? '' : ' disabled="disabled"' ) . ' size="40" value="'. $row->ordering_register .'" />';

		// build the html select list for the view level access (filtered by View Access Levels visible by the admin if not super user:
		$accessTree					=	$_CB_framework->acl->get_access_children_tree( true, false, ( ! $_CB_framework->acl->amIaSuperAdmin() ) );
		$lists['viewaccesslevel']	=	moscomprofilerHTML::selectList( $accessTree, 'viewaccesslevel', 'class="inputbox"' . ( $canEditState ? '' : ' disabled="disabled"' ), 'value', 'text', intval( $row->viewaccesslevel ), 2 );

		// We want to use View Access Levels in Joomla 1.6, and hide old method if unused:
		if ( isStillUsingGroupsForViewAccess( $row->_tbl ) ) {
			$gtree3=array();
		    $gtree3[] = moscomprofilerHTML::makeOption( -2 , '- ' . CBtxt::T('Everybody') . ' -' );
		    $gtree3[] = moscomprofilerHTML::makeOption( -1 , '- ' . CBtxt::T('All Registered Users') . ' -' );

		    if ( ( ! $_CB_framework->acl->amIaSuperAdmin() ) && in_array( (int) $row->useraccessgroupid, $_CB_framework->acl->get_groups_below_me( null, true ) ) ) {
		    	// This should always be the case:
	   			// ensure user can't add group higher than themselves:
				$gtree3 = array_merge( $gtree3, $_CB_framework->acl->get_groups_below_me() );		// vs $_CB_framework->acl->get_groups_below_me( null, true )  in tab lists
		    } else {
		    	// Just in case we missed one, and as super-admin we should see everything:
				$gtree3 = array_merge( $gtree3, $_CB_framework->acl->get_group_children_tree( null, 'USERS', false ));
		    }
			$lists['useraccessgroup']=moscomprofilerHTML::selectList( $gtree3, 'useraccessgroupid', 'size="4"' . ( $canEditState ? '' : ' disabled="disabled"' ), 'value', 'text', $row->useraccessgroupid, 2, false );
		} else {
			$lists['useraccessgroup']	=	null;
		}

		// params:
		$paramsEditorHtml			=	array();
		$options					=	array( 'option' => $option, 'task' => $task, 'cid' => $row->tabid );
	
		// additional non-specific other parameters:
		$_PLUGINS->loadPluginGroup( 'user' );
	
		$fieldsParamsPlugins		=	$_PLUGINS->getUserTabParamsPluginIds();
		foreach ($fieldsParamsPlugins as $pluginId => $fieldParamHandlerClassName ) {
			$fieldParamHandler		=	new $fieldParamHandlerClassName( $pluginId, $row );			// cbFieldParamsHandler();
			$addParamsHtml			=	$fieldParamHandler->drawParamsEditor( $options );
			if ( $addParamsHtml ) {
				$addParamsTitle		=	$fieldParamHandler->getFieldsParamsLabel();
				$paramsEditorHtml[]	=	array( 'title' => $addParamsTitle, 'content' => $addParamsHtml );
			}
		}
	
		$pluginView				=	_CBloadView( 'tab' );
		$pluginView->edittab( $row, $option, $lists, $tid, $paramsEditorHtml );
	}
	
	function saveTab( $option ) {
		global $_CB_database, $_CB_framework, $_POST;

		$this->_importNeeded();
		$this->_importNeededSave();

		if ( isset( $_POST['params'] ) ) {
		 	$_POST['params']	=	cbParamsEditorController::getRawParamsMagicgpcEscaped( $_POST['params'] );
		} else {
			$_POST['params']	=	'';
		}
	
		if ( ! isset( $_POST['tabid'] ) || ( count( $_POST ) == 0 ) ) {
			echo "<script type=\"text/javascript\"> alert('" . addslashes( CBTxt::T('Missing post values') ) . "'); window.history.go(-2); </script>\n";
			exit();
		}
		$oldrow		=	new moscomprofilerTabs( $_CB_database );
		if ( isset( $_POST['tabid'] ) && $_POST['tabid'] ) {
			$oldrow->load( (int) $_POST['tabid'] );
			// Check if user is a super user:
			if ( ! $_CB_framework->acl->amIaSuperAdmin() ) {
				// Check if user belongs to useraccessgroupid:
				if ( ! in_array( $oldrow->useraccessgroupid, $_CB_framework->acl->get_groups_below_me( null, true ) ) ) {
					echo "<script type=\"text/javascript\"> alert('" . addslashes( CBTxt::T('Unauthorized Access') ) . "'); window.history.go(-1);</script>\n";
					exit;
				}
				// Check if user belongs to viewaccesslevel:
				if ( ! in_array( $oldrow->viewaccesslevel, CBuser::getMyInstance()->getAuthorisedViewLevelsIds( false ) ) ) {
					echo "<script type=\"text/javascript\"> alert('" . addslashes( CBTxt::T('Unauthorized Access') ) . "'); window.history.go(-1);</script>\n";
					exit;
				}
			}
		}
	
		$row = new moscomprofilerTabs( $_CB_database );
		if (!$row->bind( $_POST )) {
			echo "<script type=\"text/javascript\"> alert('".$row->getError()."'); window.history.go(-1); </script>\n";
			exit();
		}
	
		// Set defaults if nothing is found
		// Also check if oldrow value to use its current value or default otherwise
		// This prevents a tab from storing to database with null values when some inputs are set disabled:
		if ( $row->useraccessgroupid == '' ) {
			$row->useraccessgroupid		=	( $oldrow->useraccessgroupid != '' ? $oldrow->useraccessgroupid : -2 );
		}
		if ( $row->viewaccesslevel == '' ) {
			$row->viewaccesslevel		=	( $oldrow->viewaccesslevel != '' ? $oldrow->viewaccesslevel : 1 );
		}
		if ( $row->ordering == '' ) {
			$row->ordering				=	( $oldrow->ordering != '' ? $oldrow->ordering : 999 );
		}
		if ( $row->ordering_register == '' ) {
			$row->ordering_register		=	( $oldrow->ordering_register != '' ? $oldrow->ordering_register : 10 );
		}
		if ( $row->enabled == '' ) {
			$row->enabled				=	( $oldrow->enabled != '' ? $oldrow->enabled : 1 );
		}
	
		$row->description	=	cleanEditorsTranslationJunk( trim( $row->description ) );
	
		if (!$row->check()) {
			echo "<script type=\"text/javascript\"> alert('".$row->getError()."'); window.history.go(-2); </script>\n";
			exit();
		}
		$row->tabid			=	(int) cbGetParam( $_POST, 'tabid', 0 );
		// Check if user is a super user:
		if ( ! $_CB_framework->acl->amIaSuperAdmin() ) {
			$canEditState			=	CBuser::getMyInstance()->authoriseAction( 'core.edit.state' );
			// Check if user belongs to useraccessgroupid
			if ( ( $row->useraccessgroupid != '' ) && ( ! in_array( $row->useraccessgroupid, $_CB_framework->acl->get_groups_below_me( null, true ) ) ) ) {
				echo "<script type=\"text/javascript\"> alert('" . addslashes( CBTxt::T('Unauthorized Access') ) . "'); window.history.go(-1);</script>\n";
				exit;
			}
			// Check if user belongs to viewaccesslevel
			if ( ( $row->viewaccesslevel != '' ) && ( ! in_array( $row->viewaccesslevel, CBuser::getMyInstance()->getAuthorisedViewLevelsIds( false ) ) ) ) {
				echo "<script type=\"text/javascript\"> alert('" . addslashes( CBTxt::T('Unauthorized Access') ) . "'); window.history.go(-1);</script>\n";
				exit;
			}
			// Check if user can edit status (and if not, that status are as expected):
			if ( ! $canEditState ) {
				$failed		=	false;
				// Check if row exists and if useraccessgroupid is different from existing row
				// Check if row doesn't exist and if useraccessgroupid is different from default
				if ( ( $oldrow->tabid && ( ( $row->useraccessgroupid != '' ) && ( $oldrow->useraccessgroupid != $row->useraccessgroupid ) ) )
				|| ( ( ! $oldrow->tabid ) && ( ( $row->useraccessgroupid != '' ) && ( $row->useraccessgroupid != -2 ) ) ) )
				{
					$failed	=	true;
				}
				// Check if row exists and if viewaccesslevel is different from existing row
				// Check if row doesn't exist and if viewaccesslevel is different from default
				// Check if user can edit status:
				if ( ( $oldrow->tabid && ( ( $row->viewaccesslevel != '' ) && ( $oldrow->viewaccesslevel != $row->viewaccesslevel ) ) )
				|| ( ( ! $oldrow->tabid ) && ( ( $row->viewaccesslevel != '' ) && ( $row->viewaccesslevel != 1 ) ) ) )
				{
					$failed	=	true;
				}
				// Check if row exists and if ordering is different from existing row
				// Check if row doesn't exist and if ordering is different from default
				// Check if user can edit status:
				if ( ( $oldrow->tabid && ( ( $row->ordering != '' ) && ( $oldrow->ordering != $row->ordering ) ) )
				|| ( ( ! $oldrow->tabid ) && ( ( $row->ordering != '' ) && ( $row->ordering != 999 ) ) ) )
				{
					$failed	=	true;
				}
				// Check if row exists and if ordering_register is different from existing row
				// Check if row doesn't exist and if ordering_register is different from default
				// Check if user can edit status:
				if ( ( $oldrow->tabid && ( ( $row->ordering_register != '' ) && ( $oldrow->ordering_register != $row->ordering_register ) ) )
				|| ( ( ! $oldrow->tabid ) && ( ( $row->ordering_register != '' ) && ( $row->ordering_register != 10 ) ) ) )
				{
					$failed	=	true;
				}
				// Check if row exists and if publish is different from existing row
				// Check if row doesn't exist and if publish is different from default
				// Check if user can edit status:
				if ( ( $oldrow->tabid && ( ( $row->enabled != '' ) && ( $oldrow->enabled != $row->enabled ) ) )
				|| ( ( ! $oldrow->tabid ) && ( ( $row->enabled != '' ) && ( $row->enabled != 1 ) ) ) )
				{
					$failed	=	true;
				}
				if ( $failed ) {
					echo "<script type=\"text/javascript\"> alert('" . addslashes( CBTxt::T('Unauthorized Access') ) . "'); window.history.go(-1);</script>\n";
					exit;
				}
			}
		}
		if ( ! $row->store() ) {
			echo "<script type=\"text/javascript\"> alert('".$row->getError()."'); window.history.go(-2); </script>\n";
			exit();
		}
	
		$row->checkin();
		cbRedirect( $_CB_framework->backendUrl( "index.php?option=$option&task=showTab" ), CBTxt::T('Successfully Saved Tab') . ": ". $row->title );
	}

}	// class CBController_tab

?>