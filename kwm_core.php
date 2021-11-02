<?php
/**
 * Plugin Name:       ci KWM TOP Block
 * Plugin URI:        https://github.com/rpi-virtuell/ci-kwm-top
 * Description:       Provides a Gutenberg block.
 * Version:           1.0.0
 * Author:            Joachim Happel
 * License:           MIT
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ci-kwm-top
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/rpi-virtuell/ci-kwm-top
 * Requires at least: 5.6
 * Requires PHP:      7.2
 */


class KwmCore {

	public function __construct() {
		add_action('init',array($this,'init'));
		add_action('admin_head',array($this,'editor_style'));
		add_action( 'wp_enqueue_scripts', array($this,'enqueue'));
		add_action( 'enqueue_block_assets',array( $this,'enqueue') );
		add_filter( 'gform_rich_text_editor_buttons', array( $this,'formular_editor_toolbar'), 10, 2 );
		add_filter( 'default_content', array( $this,'tagesordnung_template'), 10, 2 );

	}
	public function tagesordnung_template($content, $post){
		switch( $post->post_type ) {
			case 'post':
				$args = array(
					'name'        => 'vorlage-tagesorgnung',
					'post_type'   => 'post',
					'numberposts' => 1
				);
				$posts = get_posts($args);
				$content = $posts[0]->post_content;
				break;

		}
		return $content;
	}

	public function formular_editor_toolbar(){
		$mce_buttons= array( 'bold', 'italic', 'bullist', 'numlist','link', 'unlink' );
		return $mce_buttons;
	}

	public function editor_style(){
		echo '<style id="lazy_blocks_handle">
			.lazyblock .lzb-content-title {display: none;}
			.kwmtop {margin-bottom:10px!important;border-radius:  8px}
			.kwmtop .kwmicon {position:absolute; right: 0;padding: 0; margin: 0;}
			.kwmtop h2,.kwmtop h3,.kwmtop h4 {font-size: 1.5em!important; padding: 0!important; margin: 0!important;}
			.kwmtop h3 {font-size: 1.3em!important; }
			.kwmtop h4 {font-size: 1.2em!important; }
			.kwmtop p {padding-top: 0!important; margin-top: 0!important;}
			.kwmtop .wp-block{margin: 0!important;}
			.kwmtop figcaption{display: none;}
			.kwmtop figure{float:right;width:50%;}
			.kwmtop h2:before { content: attr(data-before);}
			.block-editor-block-list__layout h2:before { content: attr(data-before);}
			.kwmtop h3:before { content: attr(data-before);}
			.kwmtop h4:before { content: attr(data-before);}

			.kwmtop:before {
			    content: "";
			    position: absolute;
			    top: 1px;
			    right: var(--content-spacing);
			    width: 250px;
			    text-align: center;
			    border: 1px solid #ccc;
			    border-top: 0;
			    border-radius: 0 0 40px 40px;
			    font-size: 0.8em;
			    box-shadow: 3px 2px 4px #ccc;
			    color: #fff;
			    visibility: hidden;
			}
			.kwmtop.allgemein:before {
			    content: "Allgemeine Informationen";
			    background: #ffa52e;
			}
			.kwmtop.kommunikationsorganisation:before {
			    content: "Öffentlichkeitsarbeit";
			    background: #ffa52e;
			}
			.kwmtop.berichte:before {
			    content: "Projekte / Vorhaben und Berichte";
			    background: #ffa52e;
			}
			.kwmtop.organisationsentwicklung:before {
			    content: "Organisationsentwicklung";
			    background: #ffa52e;
			}
			.kwmtop.anderes:before {
			    content: "Anderes";
			    background: #ffa52e;
			}
			.kwmtop.openspace:before {
			    content: "Open Space";
			    background: #DCFFCC;
			}
			.kwmtop.teambuilding:before {
			    content: "Spaß / Spiel / Action";
			    background: #ffa52e;
			}
			.kwmtop.is-selected:before{
				visibility: visible;
			}
		</style>';
	}
	public function enqueue(){
		wp_enqueue_style( 'kwmtop-style', plugin_dir_url(__FILE__).'style.css' );
		wp_enqueue_script( 'kwmtop-script', plugin_dir_url(__FILE__).'/script.js', array(), '1.0.0', true );
	}
	public function init(){
		add_action( 'gform_after_submission', array($this,'on_form_submission'), 10, 2 );

	}
	public function on_form_submission($entry, $form){

		if($form["id"]<>3){
			return;
		}

		$is_openspace = false;
		$toptype = array();

		$to_post_id                 = rgar($entry,'15');
		$title                      = rgar($entry,'1');
		$content                    = rgar($entry,'17');
		$section                    = rgar($entry,'10');
		$toptype['info']            = rgar($entry,'5.1');
		$toptype['feedback']        = rgar($entry,'5.2');
		$toptype['discussion']      = rgar($entry,'5.3');
		$toptype['breakout']        = rgar($entry,'5.4');
		$toptype['contract']        = rgar($entry,'5.5');
		$time                       = rgar($entry,'4');
		$nachgereicht               = rgar($entry,'11.1');
		$responsible                = rgar($entry,'13');
		$files                     = json_decode(rgar($entry,'9'));

		$to_post = get_post($to_post_id);

		if($section == 'teambuilding'){
			$toptype=array('team'=>'fun');
		}
		if($section == 'openspace'){
			$toptype=array('openspace'=>'openspace');
			$is_openspace = true;
		}
		$content = wpautop( $content, true);
		//$content = '<!-- wp:freeform -->'.$content.'<!--/wp:freeform -->';
		$content = $this->parse_html($content);

		$icon_dir_url = plugin_dir_url(__FILE__).'icons/';

		if($is_openspace){

			$phase1_pattern = '#<!-- wp:column \{"className":"openspace-group-phase1"\} -->(.*)<!-- \/wp:column -->\W*<!-- wp:column {"className":"openspace-group-phase2"} -->#Us';
			$phase2_pattern = '#<!-- wp:column \{"className":"openspace-group-phase2"\} -->(.*)<!-- \/wp:column -->\W*<\/div>\W*<!-- \/wp:columns --><\/div>\W*<!-- \/wp:group -->#Us';

			$phase1count = $phase2count = 0;
			$phase_1 = false;
			$phase_2 = false;

			preg_match_all($phase1_pattern,$to_post->post_content,$matches);
			if($matches && isset($matches[1][0])){
				$phase1count = preg_match_all('#<!-- wp:group#Us',$matches[1][0]);
				$phase_1 = true;
			}

			preg_match_all($phase2_pattern,$to_post->post_content,$matches);
			if($matches && isset($matches[1][0])){
				$phase2count = preg_match_all('#<!-- wp:group#Us',$matches[1][0]);
				$phase_2 = true;
			}






			$template = file_get_contents(dirname(__FILE__).'/session.html');

			$this->add_attachments($content,$files,$nachgereicht);

			//replace placeholders
			$template = str_replace('{{title}}',$title,$template);
			$template = str_replace('{{content}}',$content,$template);
			$template = str_replace('{{responsible}}',$responsible,$template);

			if($phase_1 && $phase_2){
				$phase = ($phase1count > $phase2count)?'phase2':'phase1';
			}elseif ($phase_1){
				$phase= 'phase1';
			}elseif ($phase_2){
				$phase= 'phase2';
			}else{
				$phase = false;
			}

			if($phase !== false){
				$search_pattern = '#(<!-- wp:paragraph {"className":"openspace-'.$phase.'"} -->.*<!-- \/wp:paragraph -->)#sU';
			}else{
				$search_pattern = '#(<!-- wp:group {"className":"openspace-group"} -->.*<!-- wp:column {"className":"openspace-group-phase\d"} -->\W*<div class="wp-block-column openspace-group-phase\d">[^<]*)#sU';
			}
			$replace = '$1'.$template;
			$to_post->post_content = preg_replace($search_pattern,$replace,$to_post->post_content);

		}else{

			//generate image blocks with $toptype icons
			$icon_pattern ='<!-- wp:image {"id":1,"sizeSlug":"full","linkDestination":"none","style":{"color":{}}} -->
            <figure class="wp-block-image size-full"><img src="'.$icon_dir_url.'%s.svg" alt="" class="wp-image-1"/></figure>
            <!-- /wp:image -->';

			$icon = '';
			foreach ($toptype as $k=>$type){
				if(!$type){
					unset($toptype[$k]);
				}else{
					$icon .= sprintf($icon_pattern,$type);
				}
			}


			/*
			//generate paragraph blocks from shortdescription lines
			$pattern = '<!-- wp:paragraph -->
            <p>%s</p>
            <!-- /wp:paragraph -->';
			$block_contents = array();
			$paragraphs = explode("\n", $content);
			foreach ($paragraphs as $p){
				if(!empty(trim($p)))
					$block_contents[]= sprintf($pattern,trim($p));
			}

			$content = implode("\n", $block_contents);
			*/

			$this->add_attachments($content,$files,$nachgereicht);

			//get the block template
			$template = file_get_contents(dirname(__FILE__).'/block.html');

			//replace placeholders
			$template = str_replace('{{title}}',$title,$template);
			$template = str_replace('{{section}}',$section,$template);
			$template = str_replace('{{content}}',$content,$template);
			$template = str_replace('{{time}}',$time,$template);
			$template = str_replace('{{responsible}}',$responsible,$template);
			$template = str_replace('{{icon}}',$icon,$template);

			//append TOP Block to the Content (TO)
			$to_post->post_content .= $template;

		}



		//update the TO post
		wp_update_post($to_post);

		wp_redirect(home_url().'?p='.$to_post_id);


	}

	private function add_attachments(&$content,$files,$nachgereicht){
		//generate links to uploaded files
		$attch =array();
		if(count($files)<1 && !empty($nachgereicht)){
			$attch[] = array('label'=>$nachgereicht,'url'=>false);
		}else{
			foreach ($files as $file){
				$attch[] = array(
					'label'=>substr(strrchr($file,'/'),1),
					'url'=>$file,
				);
			}
		}

		//generate a list of attachment links
		$attachments = '';
		if(count($attch)>0){
			$attach_pattern = '<li><a href="%s" target="_blank">%s</a></li>';
			foreach ($attch as $att){
				$attachments .= sprintf($attach_pattern,$att['url'],$att['label']);
			}
			$content .= "\n".'<!-- wp:list -->'."\n".'<ul>'.$attachments.'</ul>'."\n".'<!-- /wp:list -->';
		}
	}

	/**
	 * Serializes a block.
	 *
	 * @param array $block Block object.
	 * @return string String representing the block.
	 */
	function serialize_block( $block ) {
		if ( ! isset( $block['blockName'] ) ) {
			return false;
		}
		$name = $block['blockName'];
		if ( 0 === strpos( $name, 'core/' ) ) {
			$name = substr( $name, strlen( 'core/' ) );
		}
		if ( empty( $block['attrs'] ) ) {
			$opening_tag_suffix = '';
		} else {
			$opening_tag_suffix = ' ' . json_encode( $block['attrs'] );
		}
		if ( empty( $block['innerHTML'] ) ) {
			return sprintf(
				'<!-- wp:%s%s /-->',
				$name,
				$opening_tag_suffix
			);
		} else {
			return sprintf(
				'<!-- wp:%1$s%2$s -->%3$s<!-- /wp:%1$s -->',
				$name,
				$opening_tag_suffix,
				$block['innerHTML']
			);
		}
	}

	public function parse_html($content){

		$updated_post_content ='';

		$doc = new DOMDocument();
		$doc->loadHTML($content);

		function showDOMNode(DOMNode $domNode,&$updated_post_content) {
			foreach ($domNode->childNodes as $node)
			{
				if(in_array($node->nodeName,array('p','ul' ))){
					//var_dump('<pre>',$node->nodeName,htmlentities($domNode->ownerDocument->saveHTML($node)),'</pre>');
					$new_content = $domNode->ownerDocument->saveHTML($node);

					$blockName = '';
					switch($node->nodeName){
						case 'p':
							$blockName    = 'core/paragraph';
							break;
						case 'ul':
						case 'ol':
							$blockName    = 'core/list';
							break;
					}
					if(!empty($blockName)){
						$new_block = array(
							// We keep this the same.
							'blockName'    => $blockName,
							// also add the class as block attributes.
							'attrs'        => array( 'className' => 'kwm-rt' ),
							// I'm guessing this will come into play with group/columns, not sure.
							'innerBlocks'  => array(),
							// The actual content.
							'innerHTML'    => $new_content,
							// Like innerBlocks, I guess this will is used for groups/columns.
							'innerContent' => array( $new_content ),
						);
						$updated_post_content .= serialize_block($new_block);
					}


				}elseif ($node->hasChildNodes()) {
					showDOMNode($node,$updated_post_content);
				}
			}
		}
		showDOMNode($doc,$updated_post_content);

		// return the content.
		return $updated_post_content;

	}

	public function get_block_from_form_entry(){

		if(!is_user_logged_in()){
			//echo 'nicht angemeldet';
			return false;
		}

		$title = wp_kses_stripslashes($_GET['title']);

		$content        = isset($_GET['description'])?   $_GET['description']       :   '';
		$section        = isset($_GET['section'])?       $_GET['section']           :   '';
		$form           = isset($_GET['form'])?          $_GET['form']              :   '';
		$time           = isset($_GET['time'])?          $_GET['time']              :   '';
		$responsable    = isset($_GET['responsable'])?   $_GET['responsable']       :   '';
		$attach         = isset($_GET['attach'])?        $_GET['attach']            :   '';
		$attach         = isset($_GET['resubmit'])?      $_GET['resubmit']          :   '';

		$block_content = '';
		return $content;

	}

	function modify($content){

		$content = get_the_content();

		$blocks = parse_blocks($content);

		foreach ($blocks as $i=>$block){

			if($block['blockName']=='kadence/tabs'){

				//add block

				break;
			}
		}

		return $this->render_content_block($content);

	}

	public function render_content_block($block){
		return apply_filters( 'the_content', render_block( $block ) );
	}
}

new KwmCore();
