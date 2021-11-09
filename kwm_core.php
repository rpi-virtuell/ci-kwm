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

		add_action( 'admin_head',array($this,'editor_style'));
		add_action( 'wp_enqueue_scripts', array($this,'enqueue'));
		add_action( 'enqueue_block_assets',array( $this,'enqueue') );
		add_action( 'enqueue_block_assets',array( $this,'editor_init') );

        add_action( 'wp_footer', array( $this,'voting_script') );

        add_action( 'blocksy:hero:after',array( $this, 'add_tagesordnungspunkt_button' ) );

		add_action( 'blocksy:single:content:bottom',array( $this,'protokoll_button') );
		add_action( 'init',array($this,'create_protokoll_from_tagesordnung'));

		add_filter( 'gform_rich_text_editor_buttons', array( $this,'formular_editor_toolbar'), 10, 2 );
		add_action( 'gform_after_submission', array($this, 'add_tagesordnungspunkt_on_form_submission' ), 10, 2 );

		add_filter( 'default_content', array( $this,'tagesordnung_template'), 10, 2 );
		add_filter( 'default_title', array( $this,'tagesordnung_title'), 10, 2 );
		add_filter( 'pre_option_default_category', array( $this,'get_default_category'), 10 );

		add_action( 'wp_insert_post', array( $this,'draft_category'), 10 ,2);

		add_action( 'delete_post', array( $this,'delete_protokoll') ,10,1);
		add_action( 'wp_trash_post', array( $this,'delete_protokoll') ,10,1);



		add_action("wp_ajax_vote_for_session" , array( $this, "vote_for_session"));
		add_action("wp_ajax_nopriv_vote_for_session" , array( $this, "vote_for_session"));
		add_action("wp_ajax_get_session_votes" , array( $this, "get_session_votes"));
		add_action("wp_ajax_nopriv_get_session_votes" , array( $this, "get_session_votes"));

		add_filter('acf/load_field/name=kwm_datum', function($field) {
			$field['default_value'] = $_GET['tag1'];
			return $field;
		});

		$this->add_custom_fields();
	}

	function get_session_votes(){

		$post_id =   json_encode($_POST['post_id']);
		$post_id = str_replace('"','',$post_id);
		$sessions = get_post_meta($post_id,'kwm_session_voting',true);

        $output = array();

		foreach ($sessions as $id=>$s){

			$usernames = [];

			$dashicons = 'insert';
            foreach ($s as $userid){
	            $user = get_userdata( $userid );
                $usernames[] = $user->display_name;
                if($userid == get_current_user_id()){
                    $dashicons = 'remove';
                }
            }
            if(count($usernames)>0){
	            $output[] = ['result-'.$id , '<ol class="session-tn"><li>'. implode('</li><li>', $usernames).'</li></ol>', $dashicons ];
            }else{
				$output[] = ['result-'.$id , '', $dashicons];
			}

		}

        echo json_encode($output);
        die();
	}
	function vote_for_session(){

        $session_id  = json_encode($_POST['session_id']);
		$session_id = intVal(str_replace('"','',$session_id));
        $joined_id  = json_encode($_POST['joined_id']);
		$joined_id = intVal(str_replace('"','',$joined_id));
        if($session_id<1){
            wp_die();
        }


		$current_user = get_current_user_id();
		//$current_user = 2;

        $post_id =   json_encode($_POST['post_id']);
		$post_id = intVal(str_replace('"','',$post_id));

        $sessions = get_post_meta($post_id,'kwm_session_voting', true);
        if(!$sessions){
	        $sessions = array();
        }

        //var_dump($joined_id,$session_id);

        if($joined_id != $session_id){
	        $user_ids = isset($sessions[$joined_id])?$sessions[$joined_id]:array();
	        if(in_array($current_user ,$user_ids)){
		        $key = array_search($current_user, $user_ids);
		        unset($user_ids[$key]);
	        }
	        $sessions[$joined_id] = $user_ids;
        }
		$user_ids = isset($sessions[$session_id])?$sessions[$session_id]:array();

		if(in_array($current_user ,$user_ids)){
			$key = array_search($current_user, $user_ids);
			unset($user_ids[$key]);
        }else{
			$user_ids[]=$current_user;
        }

		$sessions[$session_id] =  $user_ids ;
		$countjoiners = count($user_ids);
        update_post_meta($post_id,'kwm_session_voting', $sessions);

		echo json_encode(array(
                'joiners'=>$countjoiners,
                'id' => $session_id,
                'post_id' => $post_id
            ));

		wp_die();
	}
	function voting_script() {

        if(!$this->is_tagesordnung()){
            return;
        }
        ?>

        <script type="text/javascript" >
            jQuery(document).ready(function($) {


                const post_id = <?php echo get_the_ID() ?>;
                const ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ) ?>'; // get ajaxurl
                const formurl = '<?php echo home_url(  ) ?>/beitrag-zur-kwm/?type=openspace&post_id='+post_id;

                $('.openspace-group').append('<a title="Sessionvorschlag" href="'+formurl+'" class="button">+</a>');

                function create_toolbar(){

                    $('.session-pad').html('<span class="dashicons dashicons-text-page"></span>');

                    $('.session-toolbar').each(function(i,elem){
                        var btn = $('<a href="#_session_beitritt"><span class="dashicons dashicons-insert" title="Beitreten" id="btn-'+elem.id+'"></span></a>');
                        btn.on('click',function(){

                            //bereits gewählte sessions ermitteln
                            let cls = jQuery('#btn-'+elem.id).parents('.wp-block-column').attr('class');
                            let joinedSession = jQuery('.'+cls.replace(' ','.') + ' .dashicons-remove')[0];

                            let joinedSession_id = 0;
                            if(typeof joinedSession != 'undefined'){
                                joinedSession_id = Number(joinedSession.id.replace('btn-',''));
                            }

                            console.log(joinedSession_id,elem.id);

                            var data = {
                                'action': 'vote_for_session', // your action name
                                'session_id': elem.id, // some additional data to send
                                'post_id': post_id, // some additional data to send
                                'joined_id' : joinedSession_id
                            };

                            jQuery.ajax({
                                url: ajaxurl, // this will point to admin-ajax.php
                                type: 'POST',
                                data: data,
                                success: function (response) {
                                    var resp = JSON.parse(response);
                                    console.log(resp);
                                    //$('#result-'+resp.id).html(resp.joiners);
                                    read_joiner();
                                }
                            });
                        });
                        var liste = $('<div id="result-'+elem.id+'"></div>');
                        $(elem).append(btn, liste);

                    });
                }

                function read_joiner(){

                    var data = {
                        'action': 'get_session_votes', // your action name
                        'post_id': post_id // some additional data to send
                    };

                    jQuery.ajax({
                        url: ajaxurl, // this will point to admin-ajax.php
                        type: 'POST',
                        data: data,
                        success: function (response) {
                            var resp = JSON.parse(response);
                            console.log(resp);

                            for (const respElement of resp) {
                                $('#'+respElement[0]).html(respElement[1])
                                $('#'+respElement[0].replace('result','btn')).removeClass('dashicons-insert')
                                $('#'+respElement[0].replace('result','btn')).removeClass('dashicons-remove')
                                $('#'+respElement[0].replace('result','btn')).addClass('dashicons-'+respElement[2])
                                //"btn-'+elem.id+'
                            }
                        }
                    });
                }

                create_toolbar();
                read_joiner();

            });
        </script>
		<?php
	}

	public function add_tagesordnungspunkt_button(){

		global $post;

		if($post->post_type == 'post' && has_category('tagesordnung',$post)){

            if(get_post_meta($post->ID,'kwm_datum', true)> date('Ymd',strtotime('tomorrow'))){
	            ?>
                <!-- wp:buttons {"contentJustification":"right"} -->
                <div class="wp-block-buttons is-content-justification-right"><!-- wp:button {"className":"add-top-btn"} -->
                    <div class="wp-block-button add-top-btn"><a class="wp-block-button__link" href="<?php echo home_url();?>/beitrag-zur-kwm/?post_id=<?php echo $post->ID;?>">+ Top</a></div>
                    <!-- /wp:button --></div>
                <!-- /wp:buttons -->
	            <?php
            }

		}

	}

    public function protokoll_button(){

		global $post;



		if($post->post_type == 'post' && has_category('tagesordnung',$post)){

			$protokoll_id =  get_post_meta($post->ID,'protokoll_id', true);
            if(!$protokoll_id && $post->post_author != get_current_user_id()){
                return;
            }

            if(get_post_meta($post->ID,'kwm_datum', true)<= date('Ymd',strtotime('today'))){
				?>
                <!-- wp:buttons {"contentJustification":"right"} -->
                <div class="wp-block-buttons is-content-justification-right"><!-- wp:button {"className":"add-top-btn"} -->
                    <div class="wp-block-button"><a class="wp-block-button__link" href="<?php echo home_url();?>/?protokoll=<?php echo $post->ID;?>">Protokoll</a></div>
                    <!-- /wp:button --></div>
                <!-- /wp:buttons -->
				<?php
			}

		}

	}

    function delete_protokoll(int $postid){
        $args = array(
			'post_type'  => 'post',
			'meta_query' => array(
				array(
					'key' => 'protokoll_id',
					'value' => $postid,
					'compare' => '=',
				)
			),
            'numberposts' => 1
		);
		$posts = get_posts($args);
		if(isset($posts[0])){
	        delete_post_meta($posts[0]->ID,'protokoll_id');
        }

	}

	public function tagesordnung_title($title, $post){
		switch( $post->post_type ) {
            case 'post':

                $tag1 = $_GET['tag1'];
                $tag2 = $_GET['tag2'];

                $von = date('d', strtotime($tag1)).'.-'.$tag2;



				date_default_timezone_set('Europe/Berlin');
				setlocale(LC_ALL, 'de_DE.utf8');
				$title = 'Tagesordnung KWM '. $von;
	            break;

		}
		return $title;
	}

    public function tagesordnung_template($content, $post){
		switch( $post->post_type ) {
			case 'post':

				$content = file_get_contents(dirname(__FILE__).'/to-template.html');

                $tag1 = $_GET['tag1'];
				$tag2 = $_GET['tag2'];


                $tag1 = ''.date('l d.m.',strtotime($tag1)).'';
                $tag2 = ''.date('l d.m.',strtotime($tag2)).'';

				$en = array('sunday', 'monday', 'tuesday', 'wednesday' , 'thursday' , 'friday' , 'saturday');
				//$de = array('Sonntag', 'Montag', 'Dienstag', 'Mittwoch' , 'Donnerstag' , 'Freitag' , 'Samstag');
				$de = array('So', 'Mo', 'Di', 'Mi' , 'Do' , 'Fr' , 'Sa');

				$tag1 = str_replace($en,$de,strtolower($tag1));
				$tag2 = str_replace($en,$de,strtolower($tag2));



				$moderation = $_GET['moderation'];
				$zoom = $_GET['zoom'];
                $content = str_replace('{{Tag 1}}',$tag1,$content);
                $content = str_replace('{{Tag 2}}',$tag2,$content);
                $content = str_replace('{{Moderation}}',$moderation,$content);
                $content = str_replace('{{zoom}}',$zoom,$content);


				$icon_dir_url = plugin_dir_url(__FILE__).'icons/';

				$content = str_replace('{{info.svg}}',$icon_dir_url.'info.svg',$content);
				$content = str_replace('{{pause}}',$icon_dir_url.'tea-time.png',$content);
				$content = str_replace('{{fun}}',$icon_dir_url.'fun.svg',$content);
				$content = str_replace('{{fun.png}}',$icon_dir_url.'fun.png',$content);
				$content = str_replace('{{reminder}}',$icon_dir_url.'reminder.png',$content);
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
			
			
			.kwmtop {margin-bottom:10px!important;border-radius:  8px; padding: 30px!important;}
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

			.openspace-session:before,.openspace-group .wp-block-group:before,.kwmtop:before {
			    content: "";
			    position: absolute;
			    top: 1px;
			    right: var(--content-spacing);
			    width: 250px;
			    text-align: center;
			    border: 1px solid #ccc;
			    border-top: 0;
			    border-radius: 0 0 20px 20px;
			    font-size: 0.8em;
			    box-shadow: 3px 2px 4px #ccc;
			    color: #fff;
			    visibility: hidden;
			    z-index: 10;
			}
			.kwmtop.allgemein:before {
			    content: "Allgemeine Informationen";
			    background: #049ed6;
			}
			.kwmtop.kommunikationsorganisation:before {
			    content: "Öffentlichkeitsarbeit";
			    background: #e83781;
			}
			.kwmtop.berichte:before {
			    content: "Projekte, Vorhaben und Berichte";
			    background: #1b8a9b;
			}
			.kwmtop.organisationsentwicklung:before {
			    content: "Organisationsentwicklung";
			    background: #ffa52e;
			}
			.kwmtop.anderes:before {
			    content: "Anderes";
			    background: #999999;
			}
			.openspace-session:before, .openspace-group .wp-block-group:before {
			    content: "Open Space";
			    background: #00a52e;
			    width: 100px;
			}
			.kwmtop.teambuilding:before {
			    content: "Spaß / Spiel / Action";
			    background: #ffffff;
			    color: #444444;
			}
			.openspace-session.is-selected:before, .openspace-group .wp-block-group.is-selected:before, .kwmtop.is-selected:before{
				visibility: visible;
			}
			.openspace-session.has-child-selected,.kwmtop.has-child-selected,.openspace-group .wp-block-group.has-child-selected,
			.kwmtop.is-selected,.openspace-group .wp-block-group.is-selected
			{
			    padding-top: 50px;
			}
			
			
			.openspace-session.has-child-selected:before, .openspace-group .wp-block-group.has-child-selected:before,.kwmtop.has-child-selected:before{
			    visibility: visible;
			}
			.block-editor-block-list__block.trenner::before {

                width: 52%;
                display: block;
                position: absolute;
                z-index: 10;
                background: transparent;
                height: 100%;
                content: " ";
                visibility: visible;
                border: 0;
                box-shadow: none;
                border-radius: initial;
            
            }
            .trenner p {
                padding: 0 !important;
                margin: 0 auto !important;
                line-height: 1.2em;
            }
            .block-editor-block-list__block.openspace-group::before {
            
                width: 100%;
                display: block;
                position: absolute;
                z-index: 10;
                background: transparent;
                height: 80px;
                content: " ";
                visibility: visible;
                border: 0;
                box-shadow: none;
                border-radius: initial;
            
            }';
        if ( $this->is_protokoll() ):  echo '            
            
           .kwmtop{
                background-color: lightyellow!important;
            }
            h2 {
             --fontSize:26px;
             color: #899!important;
            }
            .openspace-group h3,.kwmtop h2,.kwmtop h3,.kwmtop h4{
                color: #899!important;
            }
            ';
        endif;
		echo '</style>';
	}

    public function enqueue(){
		wp_enqueue_style( 'kwmtop-style', plugin_dir_url(__FILE__).'style.css' );
		wp_enqueue_style( 'old-kwm-styles', plugin_dir_url(__FILE__).'old_styles.css' );
        //Überschriften nummeriren
        if($this->is_tagesordnung(null,true)){
	        wp_enqueue_script( 'kwmtop-script', plugin_dir_url(__FILE__).'/script.js', array(), '1.0.0', true );
        }

		wp_enqueue_style('dashicons');
	}

	public function editor_init(){

        /*global $post;
		$this->add_session_to_openspace_columns($post->ID);*/

	}

    public function create_protokoll_from_tagesordnung(){
	    if(isset($_GET['protokoll'])){

		    $topost_id = intval($_GET['protokoll']);


		    $protokoll_id =  get_post_meta($topost_id,'protokoll_id', true);

		    $protokoll = get_post($protokoll_id);

		    if($protokoll_id < 1 || $protokoll === null ){

			    $topost = get_post($topost_id);

			    if($topost){
				    $new_blocks = [];
				    $blocks = parse_blocks($topost->post_content);
				    foreach ($blocks as $block){

					    if( $block["blockName"] == 'core/heading' || (isset($block["attrs"]["className"]) &&
					                                                  strpos($block["attrs"]["className"],'kwmtop')!==false ||
					                                                  strpos($block["attrs"]["className"],'openspace-group')!==false
						    )
					    ){
						    $block["attrs"]["className"] .= ' protokoll';
						    $block['innerHTML'] = str_replace('kwmtop ', 'kwmtop protokoll ' ,$block['innerHTML']);
						    $new_blocks[]=serialize_block($block);
						    if($block["blockName"] != 'core/heading'){
							    $new_blocks[] = serialize_block( array(
								    // We keep this the same.
								    'blockName'    => 'core/paragraph',
								    // also add the class as block attributes.
								    'attrs'        => array( 'className' => 'protokoll' ),
								    // I'm guessing this will come into play with group/columns, not sure.
								    'innerBlocks'  => array(),
								    // The actual content.
								    'innerHTML'    => '<p>...</p>',
								    // Like innerBlocks, I guess this will is used for groups/columns.
								    'innerContent' => array( '<p>...</p>' ),
							    )  );
						    }

					    }


				    }
				    $content = implode("\n",$new_blocks);

				    $cat_id = get_term_by( 'slug', get_query_var( 'protokoll' ), get_query_var( 'category' ) )->term_id;

				    $arr = array(
					    'post_title' => str_replace ('Tagesordnung', 'Protokoll', $topost->post_title),
					    'post_content' =>$content ,
					    'post_type'=>$topost->post_type,
					    'post_status'=>$topost->post_status,
					    'post_category'=>array($cat_id)

				    );
				    $protokoll_id = wp_insert_post($arr);

				    if(intval($protokoll_id)>0){
					    update_post_meta($topost_id,'protokoll_id',$protokoll_id);
					    wp_redirect(home_url().'/?p='.$protokoll_id);

				    }else{
					    wp_redirect(admin_url().'/edit.php');
				    }
			    }else{
				    wp_redirect(home_url().'/');
			    }
		    }
		    wp_redirect(home_url().'/?p='.$protokoll_id);
		    exit;
	    }
    }

    public function add_session_to_openspace_columns($post,$template='default'){

        function find_openspace_columns( $blocks ){
	        $list = array();

	        foreach ( $blocks as $block ) {
		        if ( 'core/group' === $block['blockName'] && $block['attrs']['className'] == "openspace-group") {
			        // add current item, if it's a heading block
			        $list[] = $block;
		        } elseif ( ! empty( $block['innerBlocks'] ) ) {
			        // or call the function recursively, to find heading blocks in inner blocks
			        $list = array_merge( $list, my_find_heading_blocks( $block['innerBlocks'] ) );
		        }
	        }

	        return $list;
        }



        $blocks = parse_blocks(get_the_content('',false,$post));
	    $new_blocks = array();

        foreach ( $blocks as $b => $block ) {
		    if ( 'core/group' === $block['blockName'] && $block['attrs']['className'] == "openspace-group" ) {
			    // add current item, if it's a heading block
			    $spalten = $block['innerBlocks'][1]["innerBlocks"];
                $s = array();
                foreach ($spalten as $i=>$spalte){

	                $s[$i] = count($spalte["innerBlocks"]);

                }
                arsort($s);
                foreach ($s as $k=>$counts){}
                for($n  = 0; $n<=$i; $n++ ){
	                if($s[$k] == $s[$n]){
                        $k=$n;
                        break;
                    }
                }

                $innerContent = $block['innerBlocks'][1]["innerBlocks"][$k]["innerContent"];

                $last = array_pop($innerContent);
			    $innerContent[]="\n\n";
                $innerContent[]= null;
			    $innerContent[]="\n\n";
			    $innerContent[]=$last;

			    //var_dump('<pre>', $innerContent);die();

                $templateBlocks = parse_blocks($template);

                $c = $templateBlocks[0];

			    array_push($block['innerBlocks'][1]["innerBlocks"][$k]["innerBlocks"] , $c);




			    $block['innerBlocks'][1]["innerBlocks"][$k]["innerContent"]=$innerContent;

        	    $blocks[$b] = $block;




		    }
		    $new_blocks[] = serialize_block($block);

	    }

        $content = implode('',$new_blocks);
        return $content;


    }



	public function add_tagesordnungspunkt_on_form_submission($entry, $form){

		if($form["id"]!=1){
			return;
		}

        //var_dump($entry['id']);die();

		$is_openspace = false;
		$is_teambuilding = false;
		$toptype = array();

		$entry_id                   = $entry['id'];
		$to_post_id                 = rgar($entry,'15');
		$title                      = rgar($entry,'1');
		$content                    = rgar($entry,'17');
		$section                    = rgar($entry,'10');
		$toptype['contract']        = rgar($entry,'5.5');
		$toptype['breakout']        = rgar($entry,'5.4');
		$toptype['discussion']      = rgar($entry,'5.3');
		$toptype['feedback']        = rgar($entry,'5.2');
		$toptype['info']            = rgar($entry,'5.1');
		$time                       = rgar($entry,'4');
		$nachgereicht               = rgar($entry,'11.1');
		$responsible                = rgar($entry,'13');
		$files                     = json_decode(rgar($entry,'9'));

		$to_post = get_post($to_post_id);

		if($section == 'teambuilding'){
			$toptype=array('team'=>'fun');
            $is_teambuilding = true;
		}
		if($section == 'openspace'){
			$toptype=array('openspace'=>'openspace');
			$is_openspace = true;
		}
		if($is_teambuilding) {
			$content .= ' mit: '.$responsible;
		}
		$content = wpautop( $content, true);
		//$content = '<!-- wp:freeform -->'.$content.'<!--/wp:freeform -->';
		$content = $this->parse_html($content);

		$icon_dir_url = plugin_dir_url(__FILE__).'icons/';
		$this->add_attachments($content,$files,$nachgereicht);

		if($is_teambuilding) {

			$template = file_get_contents(dirname(__FILE__).'/action.html');


			$icon = $icon_dir_url.'fun.svg';

            //replace placeholders
			$template = str_replace('{{title}}',$title.' ('.$time.' Min.)',$template);
			$template = str_replace('{{content}}',$content,$template);
			//$template = str_replace('{{time}}',$time,$template);
			//$template = str_replace('{{responsible}}',$responsible,$template);
			$template = str_replace('{{icon}}',$icon,$template);

			//append TOP Block to the Content (TO)
			$to_post->post_content .= $template;



		}elseif($is_openspace){

			$template = file_get_contents(dirname(__FILE__).'/session.html');

			//replace placeholders
			$template = str_replace('{{id}}',$entry_id,$template);
			$template = str_replace('{{title}}',$title,$template);
			$template = str_replace('{{content}}',$content,$template);
			$template = str_replace('{{responsible}}',$responsible,$template);
			$template = str_replace('{{hash}}',wp_generate_password(12,false),$template);

			$to_post->post_content = $this->add_session_to_openspace_columns($to_post,$template);



        }elseif($is_openspace && false){


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
			$template = str_replace('{{id}}',$entry_id,$template);
			$template = str_replace('{{title}}',$title,$template);
			$template = str_replace('{{content}}',$content,$template);
			$template = str_replace('{{responsible}}',$responsible,$template);
			$template = str_replace('{{hash}}',wp_generate_password(12,false),$template);

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

			//get the block template
			$template = file_get_contents(dirname(__FILE__).'/block.html');

			//replace placeholders
			$template = str_replace('{{id}}',$entry_id,$template);
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

		wp_redirect(home_url().'?p='.$to_post_id.'#'.$entry_id);


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
		$doc->loadHTML('<?xml encoding="utf-8" ?>'.$content);

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

	function get_default_category()
	{
		if ( ! isset( $_GET['post_cat'] ) )
			return FALSE;

		return array_map( 'sanitize_title', explode( ',', $_GET['post_cat'] ) );
	}

	function draft_category( $post_ID, $post )
	{
		if ( ! $cat = $this->get_default_category()
		     or 'auto-draft' !== $post->post_status )
			return;

		// return value will be used in unit tests only.
		return wp_set_object_terms( $post_ID, $cat, 'category' );
	}

	/**
	 * kwm datum
	 */
    function add_custom_fields(){
	    if( function_exists('acf_add_local_field_group') ):

		    acf_add_local_field_group(array(
			    'key' => 'group_61838fe36ce87',
			    'title' => 'Datum',
			    'fields' => array(
				    array(
					    'key' => 'field_6183907685035',
					    'label' => 'Die KWM beginnt am',
					    'name' => 'kwm_datum',
					    'type' => 'date_picker',
					    'instructions' => '',
					    'required' => 1,
					    'conditional_logic' => 0,
					    'wrapper' => array(
						    'width' => '',
						    'class' => '',
						    'id' => '',
					    ),
					    'display_format' => 'F j, Y',
					    'return_format' => 'Ymd',
					    'first_day' => 1,
				    ),
			    ),
			    'location' => array(
				    array(
					    array(
						    'param' => 'post_type',
						    'operator' => '==',
						    'value' => 'post',
					    ),
					    array(
						    'param' => 'post_category',
						    'operator' => '==',
						    'value' => 'category:tagesordnung',
					    ),
				    ),
			    ),
			    'menu_order' => 0,
			    'position' => 'side',
			    'style' => 'seamless',
			    'label_placement' => 'top',
			    'instruction_placement' => 'label',
			    'hide_on_screen' => array(
				    0 => 'excerpt',
				    1 => 'discussion',
				    2 => 'comments',
				    3 => 'slug',
				    4 => 'author',
				    5 => 'format',
				    6 => 'page_attributes',
				    7 => 'featured_image',
				    8 => 'tags',
				    9 => 'send-trackbacks',
			    ),
			    'active' => true,
			    'description' => '',
		    ));

	    endif;
    }

    function is_tagesordnung($post = null,$AndProtokoll = false){
        if($post !== null){
            $post  = is_int($post)? get_post($post) : $post;
        }else{
            global $post;
        }

	    $category = array( 'tagesordnung' );
        if($AndProtokoll){
	        $category[]= 'protokoll';
        }
        if(has_category( $category, $post  )){
            return true;
        }
        return false;
    }

	function is_protokoll($post = null){
		if($post !== null){
			$post  = is_int($post)? get_post($post) : $post;
		}else{
			global $post;
		}

		$category = array( 'protokoll' );

		if(has_category( $category, $post  )){
			return true;
		}
		return false;
	}
}

new KwmCore();
