<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008 Christian Ehret 
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/


/**
 * hook to adjust linkwizard (linkbrowser)
 *
 * @author	Christian Ehret <chris@ehret.name>
 * @package TYPO3
 * @subpackage linkcommerce
 */
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');
// include defined interface for hook
require_once (PATH_t3lib.'interfaces/interface.t3lib_browselinkshook.php');

// include the treelib from commerce
require_once(t3lib_extmgm::extPath('commerce').'treelib/link/class.tx_commerce_categorytree.php');

class tx_commerce_browselinkshooks implements t3lib_browseLinksHook {
    // Sauvegarde locale du cObj parent
    protected $pObj;
    protected $treeObj;
    protected $script;
    
    // Initialisation (additionalParameters est un tableau vide)
    function init ($parentObject, $additionalParameters) {
        $this->pObj = $parentObject;
    	if ($this->isRTE()) {
				$this->pObj->anchorTypes[] = 'commerce_tab'; //for 4.3
		}   
		
		// initialize the tree
		$this->initTree();
		
		// add js
		// has to be added as script tags to the body since parentObject is not passed by reference
		$linkToTreeJs = '../../../'.t3lib_extMgm::extRelPath('commerce').'mod_access/tree.js'; //first we go from rhtml path to typo3 path
		
		$this->script  = '<script src="'.$linkToTreeJs.'" type="text/javascript"></script>';
		$this->script .= t3lib_div::wrapJS('Tree.ajaxID = "tx_commerce_browselinkshooks::ajaxExpandCollapse";');
    }
    
    function initTree() {
    	// initialiize the tree     
		$this->treeObj = t3lib_div::makeInstance('tx_commerce_categorytree');
		$this->treeObj->init();
    }
    
    // Onglets autoris�s:
    // Pour �tre affich�, l'onglet doit se trouver dans ce tableau. C'est le moment d'ajouter l'id du n�tre (ou d'en enlever...) !
    function addAllowedItems ($currentlyAllowedItems) {
        $currentlyAllowedItems[] = 'commerce_tab';
        
        return $currentlyAllowedItems;
    }
    
    // Propri�t�s des onglets:
    // Pour �tre affich�, un onglet doit �tre configur�. 
    function modifyMenuDefinition ($menuDefinition) {
        $key = 'commerce_tab';
        $menuDefinition[$key]['isActive'] = $this->pObj->act == $key;
        $menuDefinition[$key]['label'] = "Commerce";
        $menuDefinition[$key]['url'] = '#';
        $menuDefinition[$key]['addParams'] = 'onclick="jumpToUrl(\'?act='.$key.'&editorNo='.$this->pObj->editorNo.'&contentTypo3Language='.$this->pObj->contentTypo3Language.'&contentTypo3Charset='.$this->pObj->contentTypo3Charset.'\');return false;"';                    
            
        
        return $menuDefinition;
    }
    
    // Contenu du nouvel onglet
    function getTab($act) {
    	global $TCA,$BE_USER, $BACK_PATH;
    	
    	//strip http://commerce: in front of url
    	$url = $this->pObj->curUrlInfo['value'];
    	$url = substr($url, stripos($url, 'commerce:') + strlen('commerce:'));
		
		$product_uid 	= 0;
		$cat_uid 		= 0;
		
    	$linkHandlerData = t3lib_div::trimExplode('|',$url);

		foreach ($linkHandlerData as $linkData) {
			$params = t3lib_div::trimExplode(':',$linkData);
		if (isset($params[0])){
			if ($params[0] == 'tx_commerce_products') {
				$product_uid = (int)$params[1];
			} elseif ($params[0] == 'tx_commerce_categories') {
				$cat_uid = (int)$params[1];
			}
		}
		if (isset($params[2])){
			if ($params[2] == 'tx_commerce_products') {
				$product_uid = (int)$params[3];
			} elseif ($params[2] == 'tx_commerce_categories') {
				$cat_uid = (int)$params[3];
			}
		}			
		}		
		if ($product_uid > 0 && $cat_uid > 0){
			//$this->pObj->expandPage = $cat_uid;
		}
    	
    	if ($this->isRTE()) {
    			if (isset($this->pObj->classesAnchorJSOptions)) {
				$this->pObj->classesAnchorJSOptions[$act]=@$this->pObj->classesAnchorJSOptions['page']; //works for 4.1.x patch, in 4.2 they make this property protected! -> to enable classselector in 4.2 easoiest is to path rte. 
			}    
    	}
    	
    	// set product/category of current link for the tree to expand it there
    	if($product_uid > 0) {
    		$this->treeObj->setOpenProduct($product_uid);
    	}
    	
    	if($cat_uid > 0) {
    		$this->treeObj->setOpenCategory($cat_uid);
    	}
    	
    	// get the tree
    	$tree = $this->treeObj->getBrowseableTree();
    	
    	$cattable = '<h3 class="bgColor5">Category Tree:</h3><div id="PageTreeDiv">'.$tree.'</div>';
    	
    	$content = $this->script;
    	$content .= $cattable;
    	
    	//$content .= '<a href="#" onclick="return link_folder(\'commerce:tx_commerce_products:17|tx_commerce_categories:10\');">Product</a>';
    	//$content .= '<br/><a href="#" onclick="return link_folder(\'commerce:tx_commerce_products:18|tx_commerce_categories:10\');">Category</a>';
    	/*
    	$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
						'uid,title',
						'tx_commerce_categories',
						'1=1'.	t3lib_BEfunc::deleteClause('tx_commerce_categories').
							t3lib_BEfunc::versioningPlaceholderClause('tx_commerce_categories'),
						'',
						'sorting'
					);
		$cc = $GLOBALS['TYPO3_DB']->sql_num_rows($res);

		// Traverse list of records:
		$c=0;
		$cattable = '<h3 class="bgColor5">Category Tree:</h3><table id="typo3-tree" cellspacing="0" cellpadding="0" border="0">';
		
			$addPassOnParams=$this->_getaddPassOnParams();
			//$aOnClick = 'return jumpToUrl(\''.$this->thisScript.'?act='.$GLOBALS['SOBE']->browser->act.'&editorNo='.$GLOBALS['SOBE']->browser->editorNo.'&contentTypo3Language='.$GLOBALS['SOBE']->browser->contentTypo3Language.'&contentTypo3Charset='.$GLOBALS['SOBE']->browser->contentTypo3Charset.'&mode='.$GLOBALS['SOBE']->browser->mode.'&expandPage='.$v['row']['uid'].$addPassOnParams.'\');';
			$act					 = ($GLOBALS['SOBE']->browser->act)					 ? $GLOBALS['SOBE']->browser->act					 : t3lib_div::_GP('act');
			$editorNo				 = ($GLOBALS['SOBE']->browser->editorNo)			 ? $GLOBALS['SOBE']->browser->editorNo				 : t3lib_div::_GP('editorNo');
			$contentTypo3Language	 = ($GLOBALS['SOBE']->browser->contentTypo3Language) ? $GLOBALS['SOBE']->browser->contentTypo3Language	 : t3lib_div::_GP('contentTypo3Language');
			$contentTypo3Charset	 = ($GLOBALS['SOBE']->browser->contentTypo3Charset)	 ? $GLOBALS['SOBE']->browser->contentTypo3Charset	 : t3lib_div::_GP('contentTypo3Charset');
			$mode					 = ($GLOBALS['SOBE']->browser->mode)				 ? $GLOBALS['SOBE']->browser->mode					 : t3lib_div::_GP('mode');
		
			
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
			$c++;
			$bgColorClass = ($c+1)%2 ? 'bgColor' : 'bgColor-10';
			$cattable .= '<tr class="'.$bgColorClass.'">
						<td>';
			if ($cat_uid == $row['uid'] && $product_uid == 0 ){
				$cattable .='<img'.t3lib_iconWorks::skinImg($BACK_PATH,'gfx/blinkarrow_left.gif','width="5" height="9"').' class="c-blinkArrowL" alt="" />';
			} else{
				$cattable .='<img'.t3lib_iconWorks::skinImg($BACK_PATH,'gfx/clear.gif','width="5" height="9"').' class="c-blinkArrowL" alt="" />';
			}
			$cattable .= '<a href="#" onclick="return link_folder(\'commerce:tx_commerce_categories:'.$row['uid'].'\');">'.$row['title'].'</a></td>';
			
			if ($cat_uid == $row['uid'] && $product_uid > 0 ){	
			$cattable .= '<td><img'.t3lib_iconWorks::skinImg($BACK_PATH,'gfx/blinkarrow_right.gif','width="5" height="9"').' class="c-blinkArrowL" alt="" /></td>';
			} else{
				$cattable .= '<td></td>';
			}
						
			$cattable .= '<td><a onclick="return jumpToUrl(\''.$this->thisScript.'?act='.$act.'&editorNo='.$editorNo.'&contentTypo3Language='.$contentTypo3Language.'&contentTypo3Charset='.$GLOBALS['SOBE']->browser->contentTypo3Charset.'&mode='.$GLOBALS['SOBE']->browser->mode.'&expandPage='.$row['uid'].$addPassOnParams.'\');" href="#"><img height="16" width="18" alt="" src="sysext/t3skin/icons/gfx/ol/arrowbullet.gif"/></a></td>
					   </tr>';

			
					    	
		}
		$cattable .= '</table>';

		$prodtable = "";
		if ($this->pObj->expandPage>=0 && t3lib_div::testInt($this->pObj->expandPage) && $BE_USER->isInWebMount($this->pObj->expandPage))	{
		    $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
							'uid,title',
							'tx_commerce_products_categories_mm 
							LEFT JOIN tx_commerce_products ON uid = uid_local',
							'uid_foreign = '.$this->pObj->expandPage.	
		    					t3lib_BEfunc::deleteClause('tx_commerce_products').
								t3lib_BEfunc::versioningPlaceholderClause('tx_commerce_products'),
							'',
							'tx_commerce_products_categories_mm.sorting'
						);
			$cc = $GLOBALS['TYPO3_DB']->sql_num_rows($res);			
			$prodtable = '<h3 class="bgColor5">Products:</h3>';
				while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
						if ($product_uid == $row['uid']){
							$prodtable .='<img'.t3lib_iconWorks::skinImg($BACK_PATH,'gfx/blinkarrow_left.gif','width="5" height="9"').' class="c-blinkArrowL" alt="" />';
						} else {
							$prodtable .='<img'.t3lib_iconWorks::skinImg($BACK_PATH,'gfx/clear.gif','width="5" height="9"').' class="c-blinkArrowL" alt="" />';
						}
						$prodtable .= '<a href="#" onclick="return link_folder(\'commerce:tx_commerce_products:'.$row['uid'].'|tx_commerce_categories:'.$this->pObj->expandPage.'\');">'.$row['title'].'</a><br/>';
				}
		}

    	$content = '
		<!--
			Wrapper table for page tree / commerce list:
		-->
				<table border="0" cellpadding="0" cellspacing="0" id="typo3-linkPages">
					<tr>
						<td class="c-wCell" valign="top">'.$cattable.'</td>
						<td class="c-wCell" valign="top">'.$prodtable.'</td>
					</tr>
				</table>
				';    	
    	
    	$content .= '<a href="#" onclick="return link_folder(\'commerce:tx_commerce_products:17|tx_commerce_categories:10\');">Product</a>';
    	$content .= '<br/><a href="#" onclick="return link_folder(\'commerce:tx_commerce_products:18|tx_commerce_categories:10\');">Category</a>';
    	
    	*/
    	
    	
    	
        if ($this->isRTE()) {
        	$content .= $this->pObj->addAttributesForm();	
        	
    	}
    	return $content;     
    }
    
	/******************************************************************
	 *
	 * Record listing
	 *
	 ******************************************************************/
	/**
	 * For RTE: This displays all content elements on a page and lets you create a link to the element.
	 *
	 * @return	string		HTML output. Returns content only if the ->expandPage value is set (pointing to a page uid to show tt_content records from ...)
	 */
	/*function expandPageRecords() {
		
		
		global $TCA,$BE_USER, $BACK_PATH;

		$out='';
		if ($this->pObj->expandPage >= 0 && t3lib_div::testInt($this->pObj->expandPage) && $BE_USER->isInWebMount($this->pObj->expandPage))	{
			
			$tables = '*';

				// Headline for selecting records:
			$out .= $this->pObj->barheader($GLOBALS['LANG']->getLL('selectRecords').':');

				// Create the header, showing the current page for which the listing is. Includes link to the page itself, if pages are amount allowed tables.
			$titleLen=intval($GLOBALS['BE_USER']->uc['titleLen']);
			$mainPageRec = t3lib_BEfunc::getRecordWSOL('pages',$this->pObj->expandPage);
			$ATag='';
			$ATag_e='';
			$ATag2='';
			if (in_array('pages',$tablesArr))	{
				$ficon=t3lib_iconWorks::getIcon('pages',$mainPageRec);
				$ATag="<a href=\"#\" onclick=\"return insertElement('pages', '".$mainPageRec['uid']."', 'db', ".t3lib_div::quoteJSvalue($mainPageRec['title']).", '', '', '".$ficon."','',1);\">";
				$ATag2="<a href=\"#\" onclick=\"return insertElement('pages', '".$mainPageRec['uid']."', 'db', ".t3lib_div::quoteJSvalue($mainPageRec['title']).", '', '', '".$ficon."','',0);\">";
				$ATag_alt=substr($ATag,0,-4).",'',1);\">";
				$ATag_e='</a>';
			}
			$picon=t3lib_iconWorks::getIconImage('pages',$mainPageRec,$BACK_PATH,'');
			$pBicon=$ATag2?'<img'.t3lib_iconWorks::skinImg($BACK_PATH,'gfx/plusbullet2.gif','width="18" height="16"').' alt="" />':'';
			$pText=htmlspecialchars(t3lib_div::fixed_lgd_cs($mainPageRec['title'],$titleLen));
			$out.=$picon.$ATag2.$pBicon.$ATag_e.$ATag.$pText.$ATag_e.'<br />';

				// Initialize the record listing:
			$id = $this->pObj->expandPage;
			$pointer = t3lib_div::intInRange($this->pObj->pointer,0,100000);
			$perms_clause = $GLOBALS['BE_USER']->getPagePermsClause(1);
			$pageinfo = t3lib_BEfunc::readPageAccess($id,$perms_clause);
			$table='';

				// Generate the record list:
			$dblist = t3lib_div::makeInstance('TBE_browser_recordListRTE');
			$dblist->hookObj=&$this;
			$dblist->browselistObj=&$this->pObj;
			$dblist->this->pObjScript=$this->pObj->this->pObjScript;
			$dblist->backPath = $GLOBALS['BACK_PATH'];
			$dblist->thumbs = 0;
			$dblist->calcPerms = $GLOBALS['BE_USER']->calcPerms($pageinfo);
			$dblist->noControlPanels=1;
			$dblist->clickMenuEnabled=0;
			$dblist->tableList=implode(',',$tablesArr);

			$dblist->start($id,t3lib_div::_GP('table'),$pointer,
				t3lib_div::_GP('search_field'),
				t3lib_div::_GP('search_levels'),
				t3lib_div::_GP('showLimit')
			);

			$dblist->setDispFields();			
			$dblist->generateList();
			$dblist->writeBottom();

				//	Add the HTML for the record list to output variable:
			$out.=$dblist->HTMLcode;
			$out.=$dblist->getSearchBox();
		}

			// Return accumulated content:
		return $out;
	}*/
    
   /* function render($mode, $pObject) {
    	return 'Commerce Tab';
    }*/
    
    // Permet de r�cup�rer d'�ventuels param�tres
    function parseCurrentUrl ($href, $siteUrl, $info) {
			//depending on link and setup the href string can contain complete absolute link			
			if (substr($href,0,7)=='http://') {
				if ($_href=strstr($href,'?id=')) {
					$href=substr($_href,4);
				}
				else {				
					$href=substr (strrchr ($href, "/"),1);
				}
			}
				
			if (strtolower(substr($href,0,20))=='commerce:tx_commerce') {
					$parts=explode(":",$href);
					
					$info['act']='commerce_tab';
					
					
			}			
		 
        return $info;
    }
    
    // @todo: where is this used?
	/*function isValid($type, &$pObj)	{
		$isValid = false;
		$pArr = explode('|', t3lib_div::_GP('bparams'));

		if ($type === 'rte' ) {
			$isValid = true;
		}
		else {
			$valid = parent::isValid($type, $pObj);
		}
		
		return $isValid;
	} */  
	
	/**
	* returns additional addonparamaters - required to keep several informations for the RTE linkwizard
	**/
	/*function getaddPassOnParams() {
		if (!$this->isRTE()) {
						$P2=t3lib_div::_GP('P');
						return t3lib_div::implodeArrayForUrl('P',$P2);
		}
	}*/	

	/*function _getaddPassOnParams() {
		if ($this->pObj->mode!='rte') {
				if ($this->cachedParams!='') {
				
				}else {
						$P_GET=t3lib_div::_GP('P');
						$P3=array();
						if (is_array($P_GET)) {
							foreach ($P_GET as $k=>$v) {
								if (!is_array($v) && $k != 'returnUrl' && $k != 'md5ID' && $v != '')
									$P3[$k]=$v;
							}												
						}						
						$this->cachedParams= t3lib_div::implodeArrayForUrl('P',$P3);
				}
				return $this->cachedParams;
		}
	}*/
	
	private function isRTE() {
		if ($this->pObj->mode=='rte') {
			return true;
		}
		else {
			return false;
		}
		
	}	
	
	/**
	 * Makes the AJAX call to expand or collapse the categorytree.
	 * Called by typo3/ajax.php
	 *
	 * @param	array		$params: additional parameters (not used here)
	 * @param	TYPO3AJAX	&$ajaxObj: reference of the TYPO3AJAX object of this request
	 * @return	void
	 */
	function ajaxExpandCollapse($params, &$ajaxObj) {
		global $LANG;
		
		//Extract the ID and Bank
		$id   = 0; 
		$bank = 0;
		
		$PM = t3lib_div::_GP('PM');
		// IE takes anchor as parameter
		if(($PMpos = strpos($PM, '#')) !== false) { $PM = substr($PM, 0, $PMpos); }
		$PM = explode('_', $PM);
		
		//Now we should have a PM Array looking like:
		//0: treeName, 1: leafIndex, 2: Mount, 3: set/clear [4:,5:,.. further leafIndices], 5[+++]: Item UID
		
		if(is_array($PM) && count($PM) >= 4) {
			$id 	= $PM[count($PM)-1]; //ID is always the last Item
			$bank 	= $PM[2];
		}

		//Load the tree
		$this->initTree();
		$tree = $this->treeObj->getBrowseableAjaxTree($PM);
		
		//if (!$this->categoryTree->ajaxStatus) { ###CHECK THE AJAX ERROR###
		//	$ajaxObj->setError($tree);
		//} else	{
			$ajaxObj->addContent('tree', $tree);
		//}
	}
}
if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/commerce/hooks/class.tx_commerce_browselinkshooks.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/commerce/hooks/class.tx_commerce_browselinkshooks.php']);
}

?>