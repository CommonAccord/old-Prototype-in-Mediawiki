Cmacc-Mediawiki
===============

The Mediawiki extension for the Cmaac data model.  See beta.commonaccord.org.

@hazardj thinks the only things you need to do to get this working in Mediawiki are the following.  LMK.


1. Install MW
2. Add the following into your LocalSettings.php

require_once( "$IP/extensions/Binder/binder.php" );


3. In the extentions directory, create the Binder directory.  Add this file as binder.php:

Many thanks to Andrew.  Some fiddling by @hazardj under his initials - JGH. 

<?php

/***********************************************************
 * Name:     Binder
 * Desc:     Sends a playlist directly to the screen, sans skin
 *
 * Version:  0.2.0
 *
 * Author:   Swiftly Tilting (contact@swiftlytilting.com) and JGH (jh@hazardj.com)
 * Homepage: http://www.mediawiki.org/wiki/Extension:Binder
 *           http://www.swiftlytilting.com/
 *
 *
 ***********************************************************
JGH- moved missing $table to end of page. ln 139
JGH- working on qqq link marking
 */
 
$wgExtensionCredits['parserhook'][] = array(
       'name' => 'Binder',
       'author' =>'Andrew Fitzgerald', 
       'url' => 'http://swiftlytilting.com',
       'description' => 'Adds document binder functionality',
       'descriptionmsg' => "Binder-desc", // Same as above but name of a message, for i18n - string, added in 1.12.0
		 'version' => '0.2.0',
		 'path' => __FILE__,
       );


//Avoid unstubbing $wgParser on setHook() too early on modern (1.12+) MW versions, as per r35980
if ( defined( 'MW_SUPPORTS_PARSERFIRSTCALLINIT' ) ) {
	$wgHooks['ParserFirstCallInit'][] = 'efBinderSetup';
} else { // Otherwise do things the old fashioned way
	$wgExtensionFunctions[] = 'efBinderSetup';
}

function efBinderSetup() {
	global $wgParser;
	
	$theBinder = Binder::getInstance();
	
	$wgParser->setHook( 'binder', array($theBinder, 'renderBinderTag'));
	$wgParser->setHook( 'binder_values', array($theBinder, 'renderBinderValues'));
	$wgParser->setHook( 'field', array($theBinder, 'renderBinderValues'));
       return true;   
      
}


class Binder
{
	private $binderValues, $savingBinder, $renderingBinder, $usedPages, $rand, $currentPage;
	private $notMatched, $recursionLevel, $currentValueName = array();	
	private $max_level = 40;
	private static $instance;		
	
	function __construct()
	{	 global $wgTitle, $wgParser;
		$this->usedPages = array();
		$this->binderValues = array();
		$this->renderingBinder = false;
		$this->savingBinder = false;
		$this->rand = mt_rand(0,999);
		$this->currentPage = $wgTitle->getText();
		$this->notMatched = array();
		$this->recursionLevel = 0;
		
	}
	
	function renderBinderTag( $input, $args, $parser ) 
	{	global $wgParser, $wgTitle, $wgUser;
		
		  // $ret = '<form method="post" action="/w/index.php"><input type="hidden" name="title" value="'.$_REQUEST['title'].'"> 
/*
			$form = '<form method="get" action="/wiki/'.$_REQUEST['title'].'">
			
						<input type="hidden" id="doRender" name="doRender" value="true">
						<input type="submit" value="Render"> 
						<input type="submit" value="Render with Edit Links" onclick="form.doRender.value=\'editlinks\';return true;"> 
						<input type="submit" value="Save this version" onclick="form.doRender.name=\'doSave\';return true;"> 
						</form>';//*/

			$form = '<form method="get" action="/wiki/'.$_REQUEST['title'].'">
			
						<input type="hidden" id="doRender" name="doRender" value="true">
						<input type="submit" value="Render"> 
						<input type="submit" value="Render with Edit Links" onclick="form.doRender.value=\'editlinks\';return true;"> 
						</form>';//*/


		
		$wgParser->disableCache();
		
		if (preg_match_all('%<revision\s+ID\s*=\s*\'(.*)\'\s*>(.*)<\s*/revision\s*>%iU', $input, $revs))
		{	
			foreach($revs[1] as $n => $v)
			{	
				$this->usedPages[$revs[2][$n]] = $v;
			}
			
		}
		$input = preg_replace('%<revision\s+ID\s*=\s*\'(.*)\'\s*>(.*)<\s*/revision\s*>%iU', '', $input);
		
		$pages = explode("\n", trim($input));
		//JGH	

		
		if (!$this->renderingBinder && array_key_exists('doRender', $_REQUEST))
		{	// Render the binder
			
			global $wgOut;
			$ret = $this->renderBinder($pages);
			
			//find remaining "{ }" to build not-matched table
			if ($_REQUEST['doRender'] == 'editlinks')
			  {
			if (preg_match_all("%\{(.+)\}%sU", $ret, $matches))
			{	
				if (count($matches[1]) > 0)
				{
					// build not matched table
					$table = '<table class="bindervalues"><tr><th colspan=2>Unmatched values</th>';
					$unmatched = array();
					foreach($matches[1] as $n => $v)
					{	
						if (!in_array($v, $unmatched)) 
						{	$unmatched[] = $v;
							if ($v == '')
							{	
								$v = "<i>empty string</i>";
							}
							elseif (strpos($v,'{') === false)
							{
								$table .= "<tr><td>$v=</td></tr>";
							}
						}
					}
					$table .= '</table><br />';
					
					
					$ret = $ret . $table ;
				}
			}
			        $ret = preg_replace('%(<a href="/wiki/.*)(".*</a>)%Us',"$1?action=edit$2",$ret);
				$ret = preg_replace('%(<a href="http://commonaccord.org/wiki/.*)(".*</a>)%Us',"$1?action=edit$2",$ret);
				$ret = str_replace('qQq',"a",$ret);

			  }
		       
			elseif ($_REQUEST['doRender'] !== 'text')
			{
					
					$wgParser->disableCache();
					//echo $ret;
					//exit;				
			}
			
			global $wgArticle;
			$wgOut->setHTMLTitle($wgParser->mTitle->getText());
			$wgOut->setPageTitle($wgParser->mTitle->getText());
			//$wgParser->mStripState->general->data = '';
			$wgOut->addHTML($form.'<br />'.$ret);
			//print_r($wgParser);
			$wgParser->disableCache();

			$wgUser->mSkin = Skin::newFromKey('simplebinder');
			$wgUser->mSkin->setTitle($wgParser->mTitle);
//			$wgParser->mOptions->setSkin($skin);
						
			$wgOut->parserOptions($wgParser->mOptions);
			$wgOut->output();
			$wgParser->disableCache();
			$wgOut->disable();
		
			$wgParser->disableCache();
			return '';
			//return $ret;
		}	
		
		elseif (array_key_exists('showRevs', $_REQUEST))
		{	// Show revisions
			
			$rows = $this->getRevPages();
			sort($rows);
			
			$fullURL = $wgParser->mTitle->getFullURL();
			$ret  = '<table class="binderpages">';
			$ret .= '<tr><th>Versions of this binder </th></tr>';
			
			foreach($rows as $v)
			{	$v = $wgTitle->getNsText() .':'. $v->page_title;
				
				if (preg_match('%(.+)\|(.+)%', $v, $matches))
				{
					$v = '[' . $fullURL . '&oldid='.$matches[2].' '.$matches[1] . '] (Revision ID: ' . $matches[2] . ')';
					
				} else
				{
					 $v = "[[$v]]";
				}

				$ret .= "<tr><td>$v</td></tr>";
			}
			$ret .= '</table>';
			
			
			$ret = $wgParser->recursiveTagParse($ret);
			
			return $ret;
			
		}
		elseif (array_key_exists('doSave', $_REQUEST) && !$this->savingBinder)
		{	// We're saving a revision of this binder
			$this->savingBinder = true;
			
			$text = "<binder>";
			$articles = array();
			$text .= $input;
			
			
			$rendered = $this->renderBinder($pages);
			
			foreach ($this->usedPages as $n => $v)
			{ $text .= "<revision ID='$v'>$n</revision>\n";
			}
			
			
			$text .= "</binder>";
			
			$rows = $this->getRevPages();
			$count = 1;
			foreach($rows as $row)
			{	
				$title =  $row->page_title;
				
				if (($loc = strrpos($title,'/')) !== FALSE)
				{
					$count++;
				}		
			}
			
			$count = str_pad($count, 3, "0", STR_PAD_LEFT);
			
			$newpage = Title::newFromText($wgTitle->getPrefixedText().'/v'.$count);
			//if (!$newpage->exists())
			{	$newArticle =  new Article( $newpage );
				//print_r($newpage);
				$newArticle->doEdit($text,'New binder version created', 0, NULL, $wgUser);
			}
			
			return 'Your new page was created here: <a href="' . $newpage->getFullURL() .'">'. $newpage->getText() . '</a>';
		}	
		
		
		else
		{	// Don't render the binder, just show the page list
			$fullURL = $wgParser->mTitle->getFullURL();
			$ret  = '<table class="binderpages">';
			$ret .= '<tr><th colspan=2>Pages in this binder ' 
			//	. ' - [' .$fullURL.'?doRender=true Render this binder]'
			//	. ' - [' .$fullURL.'?doRender=editlinks Render with edit links]'
			//	. ' - [' .$fullURL.'?doSave=true Save this version]'
				//. ' - [' .$fullURL.'?showRevs=true Show All Versions]'*/
				. '</th></tr>';
			$masterText = 'Form';
			foreach($pages as $n=> $v)
			{	
				/*if ($n == 0)
				{	$n = $masterText;
				}*/
				
				if (preg_match('%(.+)\|(.+)%', $v, $matches))
				{
					$v = '[' . $fullURL . '&oldid='.$matches[2].' '.$matches[1] . '] (Revision ID: ' . $matches[2] . ')';
					
				} else
				{
					 $v = "[[$v]]";
				}
				/*if (($pretext = strstr($v, '|', true)) !== false)
				{
					$v = $pretext . '|' . str_replace('|', ' Revision id: ', $v);
				}*/
				++$n;
				$theText= "<tr><td style='width:7em'>$n</td><td>$v</td></tr>";
				//if ($n != $masterText)
				//{
					$ret .= $theText;
				//}
				//else
				//{	$tackOnEnd = $theText;
				//}
			}
			
			//$ret .= $tackOnEnd;
			
			// Show Revisions
			/*
			$rows = $this->getRevPages();
			sort($rows);
			
			$fullURL = $wgParser->mTitle->getFullURL();
	
			$ret .= '<tr><th colspan="2">Versions of this binder </th></tr>';
			$counter = 0;
			foreach($rows as $v)
			{	$v = $wgTitle->getNsText() .':'. $v->page_title;
				
				if (preg_match('%(.+)\|(.+)%', $v, $matches))
				{
					$v = '[' . $fullURL . '?oldid='.$matches[2].' '.$matches[1] . '] (Revision ID: ' . $matches[2] . ')';
					
				} else
				{
					 $v = "[[$v]]";
				}

				$n = $counter++ == 0 ? 'Original' : $counter-1;
				$ret .= "<tr><td>$n</td><td >$v</td></tr>";
			}
			*/
			// Show other pages
			if (count($revs[0]))
			{	
				$ret .= "<tr><th colspan='2'>Pages included in this version</th></tr>
							<tr><td>'''Revision ID'''</td><td></td></tr>";
							
				foreach($revs[1] as $n => $v)
				{	if (	$v
							&& ($theTitle = Title::newFromText($revs[2][$n]))
						 	&& ($theArticle = new Article($theTitle))
						 	&& $theArticle->exists()
						)
					{	
						$fullURL = $theTitle->getFullURL();				
						$n = '[' . $fullURL . '?oldid=' . $v . ' ' . $revs[2][$n] . ']';
					}
					else
					{
						$n = '[['.$revs[2][$n].']]';
					}
					$v = $v ? $v : 'none';
					$ret .= "<tr><td>$v</td><td>$n</td></tr>";
				}
			}		
			$ret .= '</table>';
			
									

			
			$ret = $wgParser->recursiveTagParse($ret);
			
			// $ret = '<form method="post" action="/w/index.php"><input type="hidden" name="title" value="'.$_REQUEST['title'].'"> 
			/*$ret = '<form method="post" action="/wiki/'.$_REQUEST['title'].'">
			
						<input type="hidden" id="doRender" name="doRender" value="true">
						<input type="submit" value="Render"> 
						<input type="submit" value="Render with Edit Links" onclick="form.doRender.value=\'editlinks\';return true;"> 
						<input type="submit" value="Save this version" onclick="form.doRender.name=\'doSave\';return true;"> 
						</form>'. $ret;//*/
			
			return $form.$ret;
		}
	}
	 
	function renderBinderValues( $input, $args, $parser ) 
	{	global $wgParser;
		
		funcPerformance::output(__METHOD__);
		
		if (!isset($this->binderValues))
		{	$this->binderValues = array();
		}
		
		$input = trim($input);
		
		
		$values = array();
		while (preg_match("%<item>(.+)=(.*)</item>%isU", $input, $matches))
		{	if (!array_key_exists($matches[1],$values))
			{	
				$matches[2] = str_replace('{}', '', $matches[2]);
				$values[''.$matches[1]] = $matches[2];	
				$values[str_replace(' ','_',$this->currentPage).'.'.$matches[1]] = $matches[2];
				$displayValues[''.$matches[1]] = $matches[2];
			}
			$input = preg_replace("%<item>.*</item>%isU", '', $input, 1);
		}
			
		$input = preg_replace('%\n==(.+)==%U',"\n=$1=",$input);
		
		foreach (explode("\n", $input) as $v)
		{	if ((preg_match("%(.+?)=(.*)%i", $v, $matches)) && (!array_key_exists($matches[1],$values)))
			{	$matches[2] = str_replace('{}', '', $matches[2]);				
				$values[''.$matches[1]] = $matches[2];
				$values[str_replace(' ','_',$this->currentPage).'.'.$matches[1]] = $matches[2];
				$displayValues[''.$matches[1]] = $matches[2];
			}
		}
			
		$this->binderValues = array_merge(array_map(array($this, 'parseWikiTextMap'),$values), $this->binderValues);
		//$this->binderValues = array_map(array($this, 'parseWikiTextMap'),$this->binderValues = array_merge($values, $this->binderValues));
		
		self::debugFile(print_r($this->binderValues,true));

		$this->replaceAllBinderValues();
		
		$binderTitle = array_key_exists('title',$args) ? $args['title'] : "Fields";
		if (!array_key_exists('doRender', $_REQUEST) && is_array($displayValues))
		{
			$ret = '<table class="bindervalues"><tr><th colspan=2>'. $binderTitle .'</th>';
			foreach($displayValues as $n => $v)
			{	$v = $wgParser->recursiveTagParse($v);
				$isHeader = false;
				if ($v == '')
				{	if (substr($n,0,1) == '=')
					{	$n = substr($n,1);
						$ret .= "<tr><th colspan='2'>$n</th></tr>";
						$isHeader = true;
					}
					else
					{
						$v = "<i>empty string</i>";
					}
					
				}
				if (!$isHeader)
				{	
					$ret .= "<tr><td>$n</td><td>$v</td></tr>";
				}
			}
			$ret .= '</table>';		
		
			funcPerformance::output(__METHOD__, true);
			return $ret;
		}
		funcPerformance::output(__METHOD__, true);
	}
	
	// replace all the {values} in the binder
	function replaceAllBinderValues()
	{	
		//funcPerformance::output(__METHOD__);
		$changed = true;
		//$this->debugOutput("\n>>>Entering " . __FUNCTION__ . "\n");
		while ($changed)
		{  $changed = false;
			//print_r($values);
			foreach ($this->binderValues as $value_name => $value_text)
			{  //echo '[' . $values[$value_name] . '][' . $value_text . ']';
				
				$this->binderValues[$value_name] = $this->replaceValues($value_text);
				if ($this->binderValues[$value_name] !== $value_text)
				{	//echo '[' . $values[$value_name] . '][' . $value_text . ']';
					//print_r($values);
					//exit;
					$changed = true;
				}
			}
		}
		//$this->debugOutput("\n<<<Exiting " . __FUNCTION__. "\n" );
		
		//funcPerformance::output(__METHOD__, true);
		
	}
	
	function replaceValues($string)
	{	//file_put_contents('binder.txt',print_r($this->binderValues,true));
		global $wgParser;
		
		
		
		if ($this->recursionLevel++ > $this->max_level)
		{ 	
			$this->recursionLevel--;
			
			return 'error!';
			return $this->recursive_error();
			
		}
		
		if ((memory_get_usage() / (1024 * 1024)) > 25)
		{
			echo "<b>Error: Running out of memory!</b> " . round(memory_get_usage()/(1024 * 1024)) . " MB Used";//<br /> String:  $string";
			exit;
		}
		
		//$this->debugOutput("\n>>>Entering " . __FUNCTION__ . "(($string),($values))\n");
		$changed = false;
		if (preg_match_all("%\{(.+)\}%sU", $string, $matches))
		{	
			foreach ($matches[1] as $match)
			{	$originalMatch = $match;
				/*
				if (preg_match('%(.+)\.(.+)%',$match,$innerMatches))
				{	//echo "(($match";
					//print_r($innerMatches);
					
					$innerMatches[1] = str_replace(' ','_',$innerMatches[1]);
					
					// load page if pagename.value syntax used and page not already loaded
					if (!array_key_exists($innerMatches[1],$this->usedPages))
					{	if ($titleFromText = $this->getPageInfo($innerMatches[1]))
						{	$revID = false;
							if (is_array($titleFromText))
							{	$revID = $titleFromText[1];
								$titleFromText = $titleFromText[0];
							}
							
							
							$article = new Article($titleFromText, 0);							
							$content = !array_key_exists($string, $this->usedPages) ? $article->fetchContent(): $article->fetchContent($this->usedPages[$string]);
							$this->currentPage = $titleFromText->getText();
							$wgParser->recursiveTagParse($content);
							
							//$this->updateRevID($theTitleText, $theTitle);
						}	
						
					}
					
					
					if (array_key_exists($innerMatches[1], $this->binderValues))
					{
									 					
						$match = str_replace(' ','_',$this->binderValues[$innerMatches[1]]).".".$innerMatches[2];										
					}
					else
					{
							$match = $innerMatches[1].".".$innerMatches[2];										
					}
					//echo "|$match))";
					
				}
				*/
				
				$realMatch = $match;
				$nameSpace = '';
				//echo "\n(($match))";
				if (preg_match('%(.+):(.*)%', $match, $innerMatches))
				{	$nameSpace = $innerMatches[1];
					// add : if there is any namespace text;
					$nameSpace = $nameSpace ? $nameSpace . ':' : $nameSpace;
					$match = $innerMatches[2];
					//print_r($innerMatches);
				}
				$theTitleText = $nameSpace .$match;
				
				
				//file_put_contents('binder.txt',print_r($match,true).print_r($this->binderValues,true) ."\n", FILE_APPEND);	
				
				if (($inArray = array_key_exists($match, $this->binderValues) )
						|| (($theTitle = Title::newFromText($match))
								&& ($theArticle = new Article($theTitle, 0))
								&& ($theArticle->exists())				
								&& (array_key_exists($theTitleText, $this->usedPages) 
									? $this->usedPages[$theTitleText] != false 
									: true)
							)
					)
				
				{	//$this->debugFile("\nMatched: $match , isval: $inArray" . print_r($this->binderValues,true));
					//echo "(!$match))";
					if ($inArray)
					{	
						$content = $this->binderValues[$match];
						//$this->debugOutput("\n$match=$content\n\n\n");
						$content = $this->trimList($content);
					}
					else
					{				
						$theArticle = new Article($theTitle, 0);
						$content = !array_key_exists($string, $this->usedPages) ? $theArticle->fetchContent(): $theArticle->fetchContent($this->usedPages[$string]);
						$content = preg_replace('%\n?<noinclude\>(.*)\</noinclude\>\n?%isU','',$content);
						$content = preg_replace("%#REDIRECT\s*%s", '', $content);
						//$this->debugOutput($match);
						$content = $this->renderWikiText($content);
						$content = $this->trimList($content);
						
						$this->updateRevID($theTitleText, $theTitle);
						
					}
					//$content = $this->renderWikiText($content);
					
					$content = $this->parseLinks($content);
					//jgh
					$content="<qQq href='Link#{$match}'>{$content}</qQq>";

					
					$string = str_replace('{'.$originalMatch.'}',$content , $string);
					$changed = true;
										
					
					/*if (array_key_exists($match,$this->notMatched))
					{	unset($this->notMatched[$match]);
					}*/
				}           
				else        
				{ //$this->notMatched[$match] = $match;
					
				}
			}
		}
		//$this->debugOutput("\n<<<Exiting " . __FUNCTION__. "\n" );
		$ret = ($changed ? $this->replaceValues($string) : $string);
		$this->recursionLevel--;
		return $ret;
	}

	
	function parseLinks($content)
	{	
		if ($this->recursionLevel++ > $this->max_level)
		{ 	
			$this->recursionLevel--;
			
			return  $this->recursive_error();
			
		}
		
		if ((memory_get_usage() / (1024 * 1024)) > 25)
		{
			echo "<b>Error: Running out of memory!</b> " . round(memory_get_usage()/(1024 * 1024)) . " MB Used";//<br /> String:  $string";
			exit;
		}
		
		//$this->debugOutput("\n>>>Entering " . __FUNCTION__ . "($content)\n");
		$content = preg_replace("%#REDIRECT\s+%s", '', $content);
		$changed = false;
		global $wgParser, $wgUser;
		
		// start search for links
		if (preg_match_all("%\[\[(.+)\]\]%sU", $content, $matches))
		{	
			//print_r($matches);
			
			foreach ($matches[1] as $n => $match)
			{	$realMatch = $match;
				$nameSpace = '';
				//echo "\n(($match))";
				if (preg_match('%(.+):(.*)%', $match, $innerMatches))
				{	$nameSpace = $innerMatches[1];
					// add : if there is any namespace text;
					$nameSpace = $nameSpace ? $nameSpace . ':' : $nameSpace;
					$match = $innerMatches[2];
					//print_r($innerMatches);
				}
				$tail = false;
				$isUnderscore = false;
				
				// look for pipe, if so, don't match
				
				//if ((($tail = strstr($match, '|')) !== false) && (($isUnderscore = strpos($tail, '_')) !== false))								
				if ((($tail = strstr($match, '|')) !== false))				
				{	
					$match = substr($match,0,strpos($match,'|'));
					//$theTitleText = $match;
					//echo "!?!$realMatch";
				}
				else
				{  //$theTitleText = $nameSpace .$match;
				}
	
				
				if (($tail === false) || ($isUnderscore !== false))
				{	$theTitle = false;
					$theTitleText = $nameSpace .$match;
					
					if (($isValue = array_key_exists($match, $this->binderValues))
						||	(($nameSpace !== 'Category:') 
								&& (  ($theTitle = Title::newFromText($theTitleText))
									&& ($linkedArticle = new Article($theTitle, 0))
									&& ($linkedArticle->exists())
									&& (array_key_exists($theTitleText, $this->usedPages) ? $this->usedPages[$theTitleText] != false : true)
									)
							)
						)
					{	
						//$this->debugFile("\nMatched: $match , isval: $isValue" . print_r($this->binderValues,true));
						if ($isValue)
						{	
							$fetchContent = $this->binderValues[$match];
						}
						else
						{ 
							$fetchContent = !array_key_exists($theTitleText, $this->usedPages) ? $linkedArticle->fetchContent(): $linkedArticle->fetchContent($this->usedPages[$theTitleText]);
							$fetchContent = preg_replace('%\n?\<noinclude\>(.*)\</noinclude\>\n?%isU','',$fetchContent);
						}
						$fetchContent = preg_replace("%#REDIRECT\s*%s", '', $fetchContent);
						$fetchContent = $this->renderWikiText($fetchContent);
						$fetchContent = $this->trimList($fetchContent);
						$content = str_replace($matches[0][$n], $fetchContent, $content);
						//$this->usedPages[$matches[1][$n].':'.$match] = $theTitle->getLatestRevID();
						
						$changed = true;
						/*if (array_key_exists($match,$this->notMatched))
						{	unset($this->notMatched[$match]);
						}*/
					}				
					else
				
					{ //$this->notMatched[$match] = $match;
					}
					
					$this->updateRevID($theTitleText, $theTitle);
					
				}

			}
		}
		
	
		//$content = $wgParser->recursiveTagParse($content);
		//echo $content;
		
		//recurse the text to check for more links
		
		//$this->debugOutput("\n<<<Exiting " . __FUNCTION__. "\n" );
		$ret = ($changed ? $this->parseLinks($content) : $content);
		$this->recursionLevel--;
		return $ret;
		
	}
	
	function getPageInfo($text)
	{	$ret = array();
		
		
		
		if (strstr($text,'|') !== false)
		{	$pieces = explode('|',$text);
			if ($ret[] = $theTitle = Title::newFromText($pieces[0]))
			{	$ret[] = $pieces[1];
				$this->updateRevID($pieces[0], $theTitle);
				//$this->usedPages[$pieces[0]] = $theTitle->getLatestRevID();
				return $ret;
			}
			else 
			{	
				return false;
			}
		}else
		{	$ret = Title::newFromText($text);
			$this->updateRevID($text, $ret);
			//$this->usedPages[$text] = $ret->getLatestRevID();
			return $ret;
		}
	}
	
	function updateRevID($page, $title)
	{			
		if ($title && !array_key_exists($page, $this->usedPages))
		{
			$this->usedPages[$page] = $title->getLatestRevID();
		}
		
		//self::debugFile($page . " ". $this->usedPages[$page] ."\n");
	}
	
	function renderBinder($pages)
	{	global $wgParser, $wgTitle;
		self::debugFile("rendering binder ===============\n");
		set_time_limit  (  6000  );
		
		funcPerformance::output('RENDER');
		
		$this->renderingBinder = true;
		
		$time_start = microtime(true);
		
		// load the values from the various pages
		funcPerformance::output('LOAD VALUES');
		self::debugFile(print_r($pages,true));
		
		$allValues = '';
		foreach ($pages as $n => $v)
		{
			//if ($n !== 0)			
			//{	
				if ($titleFromText = $this->getPageInfo($v))
				{	$revID = false;
					if (is_array($titleFromText))
					{	$revID = $titleFromText[1];
						$titleFromText = $titleFromText[0];
					}
					$article = new Article($titleFromText, 0);
					$this->currentPage = $titleFromText->getText();
					$content = !array_key_exists($v, $this->usedPages) ? $article->fetchContent() : $article->fetchContent($this->usedPages[$v]);
					
					$matches = array();
					$wgParser->extractTagsAndParams(array('field'),$content, $matches);
					
					self::debugFile(print_r($matches,true));
					
					foreach( $matches as $marker => $data ) {
						list( $element, $content, $params, $tag ) = $data;
						$tagName = strtolower( $element );
						if( $tagName == 'field'  ) {
							$output = $this->renderBinderValues($content, $params, $wgParser, false );
						}
					}
			//		$allValues .= $content;
					
					//$wgParser->recursiveTagParse($content);
				}	
				
			//}
			
		}	
		
		
		//$wgParser->recursiveTagParse($allValues); 
		
		// pull the actual document template	
		if ($titleFromText = $this->getPageInfo($pages[0]))
		{
			
			$revID = false;
			if (is_array($titleFromText))
			{	$revID = $titleFromText[1];
				$titleFromText = $titleFromText[0];
			}
			
			$article = new Article($titleFromText, 0);
			//$content = !array_key_exists($pages[0], $this->usedPages) ? $article->fetchContent() : $article->fetchContent($this->usedPages[$pages[0]]);
			$content = '{Model.Root}';
			// Render all HTML except links		
			//$this->debugOutput('Round1');
			
			//funcPerformance::output('round1');
						
			$content = $this->renderWikiText($content);
			
			// Recurse links
			$content = $this->parseLinks($content);
			
			//funcPerformance::output('round1', true);
			
			//funcPerformance::output('round2');
			//$this->debugOutput('Round2');
			$content = $this->renderWikiText($content, false);
			
			// Replace values 
			$content = $this->replaceValues($content);
			
			//funcPerformance::output('round2', true);
			
			//funcPerformance::output('round3');
			//$this->debugOutput('Round3');
			$content = $this->renderWikiText($content, false);

			//funcPerformance::output('round3', true);
			// do some final clean up
			$content = $this->cleanEmptyListItems($content);
			
			// display render time
			$time_end = microtime(true);
			$time = $time_end - $time_start;
			$content .= "<!-- Binder Rendered in:  $time seconds -->";
			//self::debugFile("==== done rendering binder ===============\n");
			
			//self::debugFile("==== peak mem: " . memory_get_peak_usage() . " ===============\n");
			//funcPerformance::output('RENDER', true);
			return $content;
			
		}
		else
		{	
			//funcPerformance::output('RENDER', true);
			return '';
		}
		
	}
	
	function getRevPages()
	{	global $wgTitle;
		$namespace = $wgTitle->getNamespace();
		$title = $wgTitle->getText();
		if (($loc = strrpos($title,'/')) !== FALSE)
		{
			$shorttitle = substr($title, 0, $loc);
			
		} else
		{	$shorttitle = $title;
		}
		
		$ret = '';
		$shorttitle = mysql_escape_string(str_replace(' ','_',$shorttitle)); 
		$ret .= $shorttitle;
		$ret .= $namespace;
		//exit;
		$dbr = wfGetDb(DB_SLAVE);
		$res = $dbr->select('page',
									'page_title',
									" page_namespace = ". mysql_escape_string($namespace) . " 
										  AND `page_title` REGEXP '^$shorttitle(/.+)?$'"	,
									__METHOD__, 
									'');
										
		$row = $dbr->fetchObject($res);
		
		$output = array();
		$count = 1;
		
		while($row)
		{	
			$output[] = $row;
			
			$row = $dbr->fetchObject($res);
		}
		return $output;
	}
	
	function trimList($str)
	{  //$this->debugOutput("\n>>>Entering " . __FUNCTION__ . "($str)\n");
		$str = trim($str);
	
		if (preg_match('%^<[u|o]l>\s*<li>(.*)</li>\s*</[u|o]l>$%Usi',trim($str), $matches))
		{	$str =  $matches[1];
		} 
		
		
		
		//$this->debugOutput("\n<<<Exiting " . __FUNCTION__ . "($str)\n");
		return $str;
	}
	
	function cleanEmptyListItems($content)
	{				
		//$content = preg_replace('%<ol>\s*<li>[\s|(<p>)|(</p>)]*<ol>\s*<li>(.*)</li>\s*</ol>[\s|(<p>)|(</p>)]*</li>\s*</ol>%Uis',"<ol><li>$1</li></ol>",$content);
		
		$content = preg_replace('%<li>[\s;.,]*</li>%Usi','',$content);
		$content = preg_replace('%<li>\s*</li>%Usi','',$content);
		$content = preg_replace('%<li>\s*<ol>\s*</ol>\s*</li>%Usi','',$content);
		$content = preg_replace('%<li>\s*<li>%Usi','<li>',$content);
	
		$content = preg_replace('%</p>\s*<p>\s*<br />\s*</p>\s*</li>%Usi','</p></li>',$content);
		
		// remove double lists
		//$content = preg_replace('%<li><ol>(<li>.*</li>)*</ol></li>%Usi','$1',$content);
		//$content = preg_replace('%<li>(.*)</li></ol></li>%Usi','<li>$1</li>',$content);
		
		//$content = preg_replace('%<ol>\s*<li>\s*</p>\s*<ol>\s*<li>(.*)</li>\s*</ol>\s*<p>\s*</li>\s*</ol>%Usi',"<ol><li>$1</li></ol>",$content);
		return $content;
		
	}
	
	function renderWikiText($content, $saveLinks = true, $newline =true)
	{	global $wgParser, $wgTitle, $wgUser;
		
		funcPerformance::output(__METHOD__);
		
		$rand = $this->rand;
		//$this->debugOutput("\n(pre($content))\n");
			
		// protect some code from being parsed
		$brackets = array('<','>', '://');
		$entities = array( "(`lt`$rand)","(`gt`$rand)", "(`url`$rand)");
		$content = preg_replace('%\s*\[\[Category:(.+)\]\]\s*%Usi','',$content);
		
		if ($saveLinks)
		{	$content = preg_replace('%\[\[(.*)\|(.*)\]\]%Usi',"[[$1(`pipe`".$rand.")$2]]",$content);
			$brackets[] = '[[';
			$entities[] = "(`open`$rand)";
			
			$brackets[] = ']]';
			$entities[] = "(`close`$rand)";
		}
		
		$content = str_replace($brackets,$entities, $content);
		
		// parse it!
		$content = $wgParser->parse($content, $wgTitle, ParserOptions::newFromUser( $wgUser ),$newline , false);
		$content = $content->getText();
		
		// restore protected code
		if ($saveLinks)
		{
			$brackets[] = '|';
			$entities[] = "(`pipe`$rand)";
		}
		
		$content = str_replace($entities,$brackets, $content);
		
		// clean some added html
		$content = preg_replace('%^<p>(.*)</p>$%Uis',"$1",$content);
		$content = preg_replace('%^(\s*)<ol><li>(.*)</il></ol>(\s*)$%Uis',"$1$2$3",$content);
		
		//$this->debugOutput( "\n(post($content))\n");
		funcPerformance::output(__METHOD__, true, $content);
		
		return $content;
	}
	
	// helper function for updating links
	// used with array_map in renderBinderValues
	function parseWikiTextMap($content)
	{	
		//$this->debugOutput("\n>>>Entering " . __FUNCTION__ . "($content)\n");

		
		$content = $this->parseLinks($content);
		$content = $this->renderWikiText($content, true, false);

		return $content;

	}
	function debugOutput($text)
	{	
		if (array_key_exists('debug',$_REQUEST))
		{	echo $text;
		}
	}
	
	function debugFile($text)
	{	//file_put_contents('binder.txt', $text, FILE_APPEND);
	}
		
	// For static instantiation
	public static function getInstance()
   {
       if (!isset(self::$instance)) {
           $c = __CLASS__;
           self::$instance = new $c;
       }

       return self::$instance;
   }
   
   function recursive_error()
   {
   	$ret =  "<b>Error: Maximum Recursion Level ( $this->max_level ) reached.  It is likely you have a circular reference in the document. " ;//<br /> String:  $string";
			//$ret .=  substr($string, 0, 100);
		echo $ret;
		//exit;	
		return;// $ret;
			
		
   }
     
}
      
class funcPerformance
{
	
	static $memUsage, $times;
	
	static function output($funcName, $isExiting = false, $content = '')
   {
   	if ($isExiting == false)
   	{
   		self::$memUsage[$funcName]	 = memory_get_peak_usage(true);
   		self::$times[$funcName]	 = microtime(true);
   		Binder::debugFile("Entering: $funcName\t\tMem:" . memory_get_peak_usage(true) . "\n");
   	}
   	else
   	{
   		$diff = (memory_get_peak_usage(true) - self::$memUsage[$funcName]);
   		Binder::debugFile("Exiting: $funcName\t\tMem:" . memory_get_peak_usage(true) 
   			. " - diff: " . $diff . "\ttime diff:" 
   			.  (microtime(true) - self::$times[$funcName]) 
   			.  (($content) ? "[".substr($content,0,40)."]" : '') . " \n\n");
   		unset(self::$memUsage[$funcName]);
   	}
   	
   }
}
