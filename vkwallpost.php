<?php
/*
 Plugin Name: VKWallPost
Plugin URI: http://pavelk.ru/portfolio/VKWallPost
Description: Публикация на стене ВКонтакте постов wordpress
Version: 0.1
Author: PavelK
Author URI: http://pavelk.ru
*/


require_once('vkapi.php');
require_once('listtablegroups.php');

class VKWallPost extends VKapi {

	function __construct(){
		if (!is_admin()) return;
		
		//-- Подгрузим язык
		define('TEXTDOMAIN', 'default');
		load_plugin_textdomain(TEXTDOMAIN, PLUGINDIR.'/'.dirname(plugin_basename(__FILE__)).'/langs');
			
		include 'setup.php';
		$setup=new SetupVKWP(__FILE__);

		//-- Инициализируем класс VKapi и необходимые переменные
		parent::__construct();
		$this->_app_id=3563905;
		$this->_key='jGdC0q69Ba47Tw8PoCG5';
		$this->_client_id = 71074831;
		$this->_access_token='551d66fd4df06054ebb6ba23bc8b6963d35f39bbfc28c38ce5ce58170bdef17a9e7e1843a5de241721092';
		$this->_group_id=23914086;
		
		//-- Добавляем обработчики событий
		add_action('init', array($this, 'init'));
		add_action('admin_menu', array(&$this, 'admin_menu'));
		add_action('admin_init', array(&$this, 'admin_init'));
		
		add_action('wp_ajax_exportaction', array(&$this, 'ajax_exportaction'));
		add_action('publish_post', array(&$this, 'publish_post'), 1);

		add_action('edit_tag_form_fields', array($this, 'category_form_fields'), 10, 2);
		add_action('edit_category_form_fields', array($this, 'category_form_fields'), 10, 2);

		add_action('edited_terms', array($this, 'edited_term_taxonomies'), 10, 2);

		add_action('admin_notices', array($this, 'showAdminMessages'), 10000);
		
			
	}

	public function init() {
		global $wpdb;
		wp_localize_script( 'VKWallPost', 'VKWallPost', array());

		wp_register_script('VKWallPost', plugins_url('/vkwallpost.js', __FILE__ ), array('jquery'));
		wp_register_style('VKWallPost',plugins_url('/vkwallpost.css', __FILE__ ));
		

		$wpdb->vkmeta = "{$wpdb->prefix}vkmeta";
		$wpdb->vktemp = "{$wpdb->prefix}vktemp";
	}



	public function admin_menu() {
		$this->pagehook=add_menu_page( "VKWallPost", __('VKWallPost', TEXTDOMAIN), 5, 'VKWallPost',  array(&$this, 'menu_vkwallpost'));
		add_submenu_page("VKWallPost", __('Export', TEXTDOMAIN), __('Export', TEXTDOMAIN), 5, 'Export', array(&$this, 'menu_export'));
	}
	
	public function admin_init() {
		//-- регистрируем настройки
		$settings=array(
				array(
						'sectionName'=>'eg_setting_section',
						'descr'=>'Example settings section in reading',
						'page'=>'general_options',
						'fields'=>array(
								array('name'=>'appid', 'title'=>'App ID', 'descr'=>'Descr text', 'type'=>'text')
								,array('name'=>'appkey', 'title'=>'App Key', 'descr'=>' descr descr descr', 'type'=>'text')
								,array('name'=>'clientid', 'title'=>'Client ID', 'descr'=>' descr descr descr', 'type'=>'text')
								,array('name'=>'accesstoken', 'title'=>'Access token', 'descr'=>' descr descr descr', 'type'=>'text')
						)
				)
		);
		
		foreach ($settings as $section) {
			add_settings_section($section['sectionName'], __($section['descr']), array(&$this, 'setting_section_callback'), $section['page']);
			foreach($section['fields'] as $field) {
				add_settings_field($field['name'], __($field['title']), array(&$this, 'setting_field_callback'), $section['page'], $section['sectionName'], $field);
				register_setting($section['page'], $field['name']);
			}
		}
			
	}

	function menu_vkwallpost() {

		
		$optSection=isset($_GET['tab'])? $_GET['tab'] : 'general_options';
		$tabs=array(
				array("general_options", 'General options'),
				array("other_options", 'Other options')
		);
		$tabs=apply_filters("vkwallpost_settings_tabs", $tabs);
		echo '
		<div class="wrap">
		<div id="icon-themes" class="icon32"></div>
		<h2>'.__('VKWallPost options').'</h2>
		<h2 class="nav-tab-wrapper">
		';
		settings_errors();
		foreach ($tabs as $tab) {
			$isTabActive=($tab[0]==$optSection)? 'nav-tab-active' : '';
			echo "<a href='?page=VKWallPost&tab={$tab[0]}' class='nav-tab {$isTabActive}'> {$tab[1]} </a> ";
		}
		echo '
		</h2>
		<form method="POST" action="options.php">
		';
		
			settings_fields($optSection);
			do_settings_sections($optSection);
			submit_button();
		
		echo'
		</form>
		</div>
		';

	}

	function setting_section_callback() {
		//
	}
	
	function setting_field_callback($field) {
		echo "<input name='{$field['name']}' id='edit_{$field['name']}' size='100' type='{$field['type']}' value='".htmlspecialchars(get_option($field['name']))."' class='' /> {$field['descr']}";
	}
	
	function menu_export() {
		global $wpdb;
		
		wp_enqueue_script("VKWallPost");
		wp_enqueue_style("VKWallPost");
		
		if (isset($_POST['updateenableitems'])) {
			$items=$_POST['cb_export'];
			foreach ($items as $id => $item) {
				$wpdb->update( $wpdb->vktemp , array("enable"=>($item=="true")? 1 : 0) , array("ID"=>$id), array("%d"), array("%d") ); 
			} 
		}
		
		$listTable=new VKWP__List_Table($this);
		$listTable->prepare_items($this->listPosts());
		$enableItemsCount = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->vktemp} WHERE enable=1" );
		
		echo '
		<div id="vkwp" class="wrap">
		'.screen_icon("options-general").'
		<h2>VKWallPost export</h2>
		<div id="message" class="updated below-h2">
		<p>'.__('Welkom to export!').'</p>

		</div>
		<div>
		<a id="startexport" class="button" href="javascript:;">'.__('Start export').'</a>
		<div class="media-item">
		<div class="progress" style="float:left; width:100%">
		<div class="percent">'.'0 '.__('of').' '.$enableItemsCount.'</div>
		<div class="bar" ></div>
		</div>
		</div>
		</div>
		<div><form method="post">';
		$listTable->display();
		echo 	'<input type="submit" class="button" value="'.__('Update').'" /><input type="hidden" name="updateenableitems" value="1" /></form></div>
		</div>
		';
		
		
		wp_localize_script( 'VKWallPost', 'vkmeta', array( 'total' => $enableItemsCount ));
	}

	function listPosts() {
		global $wpdb;		

		$wpdb->query("
				INSERT INTO {$wpdb->vktemp} (vk_id, exportToVK, exportToAlbum, ID, post_title, post_content, enable)
				SELECT m.vk_id, IF(m.meta_value  = 'true' , 1 , 0) as exportToVK, m2.meta_value as exportToAlbum, p.ID, p.post_title, p.post_content, 1 FROM  `{$wpdb->vkmeta}` as m
				LEFT JOIN `{$wpdb->term_relationships}` as `rs` ON rs.term_taxonomy_id=m.vk_id
				LEFT JOIN `{$wpdb->vkmeta}` as m2 ON m2.vk_id=m.vk_id
				LEFT JOIN `{$wpdb->vkmeta}` as m3 ON m3.meta_key='postExportDT' and  m3.vk_id=rs.object_id
				RIGHT JOIN `{$wpdb->posts}` as p ON p.ID=rs.object_id and CAST(p.post_modified as DATETIME)>=CAST(IFNULL(m3.meta_value, '0000-00-00')  AS DATETIME)
				LEFT JOIN `{$wpdb->vktemp}` as t ON t.ID=p.ID
				WHERE ((m.meta_key='exportToVK' AND m.meta_value='true') OR (m2.meta_key='exportToAlbum' and m2.meta_value>-2))  AND (t.ID IS null) ORDER BY p.post_date 
		");
	
		$items=$wpdb->get_results("SELECT * FROM {$wpdb->vktemp} ORDER BY tid DESC");
		
		//-- получим все альбомы и создадим массив по их айдишникам
		$_albums=$this->invoke("photos.getAlbums", array(
			'owner_id'=>-$this->_group_id
		));
		
		if (!$_albums) return;
		
		$albums=array();
		
		$albums[-1]=(object)array("title"=>__("Without album"));
		
		foreach ($_albums as $album) {
			$albums[$album->aid]=$album;
		}

		
		foreach ($items as $item) {
			$res[]=array('id'=>$item->ID, 'title'=>$item->post_title, 'album'=>$albums[$item->exportToAlbum]->title, 'export'=>$item->enable);
		}
		
		
		return $res;
		
	}
	
	function ajax_exportaction() {
		global $wpdb;
		$_export=urldecode($_GET['export']);
		$export  = json_decode($_export);
		$limit=1; //TODO: Добавить в настройки сколько за раз
		$posts=$wpdb->get_results("SELECT * FROM {$wpdb->vktemp} WHERE enable=1 ORDER BY tid ASC LIMIT {$limit} ;");

		$nowDT = new DateTime();
		$nowDT=$nowDT->format('Y-m-d H:i:s');		
		
		foreach ($posts as $post) {
			if (!$post->enable) continue;	
			
			$hasPostThumb=has_post_thumbnail($post->ID);
			$hasGallery=( get_post_gallery($post->ID, false )!==false );
			
			if ($post->exportToVK==true) {
				$attachments=array();
				//-- прогрузим картинки для сообщения стены
				if ( $hasPostThumb || $hasGallery ) { 
					$vkUPServer=$this->invoke("photos.getWallUploadServer", array(
							"gid"=>$this->_group_id,
							"save_big"=>1
					)); //TODO: А надо ли каждый раз? О_о
					if (!$vkUPServer) die("Problem with get upload server"); //-- ошибка с vkapi.php, пусть сам разбирается.
				
					$imagesID=array();
					
					if ($hasPostThumb) {
						$imagesID[]=get_post_thumbnail_id($post->ID);
					}
					
					if ( $hasGallery) { 
						$gallery = get_post_gallery($post->ID, false );
						$ids=explode(",", $gallery['ids']);
						$imagesID+=$ids;
					}
	
					foreach ($imagesID as $imageID) {
						$imagePath=get_attached_file($imageID);
					
						$upFileData=$this->uploadFile($vkUPServer->upload_url, array('photo'=>"@{$imagePath}"));
						if (!$upFileData) {
							var_dump($imagesID);
							var_dump($gallery);
							die("Problem with upload file 1");
						}
	
						$saveFileData=$this->invoke("photos.saveWallPhoto", array(
								"server"=>$upFileData->server,
								"photo"=>$upFileData->photo, //TODO: Доки говорят, что внутри может быть другой json
								"hash"=>$upFileData->hash,
								"gid"=>$this->_group_id,
								"caption"=>$post->post_title
						));
						if (!$saveFileData) die("photos.saveWallPhoto");					
						$attachments[]=$saveFileData[0]->id;
					}	
				}
				

				//-- Добавим ссылку на категорию
				$taxonomy_names = get_object_taxonomies( get_post((int)$post->ID) );
				$attachments[]=get_term_link( (int)$post->vk_id, $taxonomy_names[0]);
				
				//-- публикуем пост
				$postVK=$this->invoke('wall.post', array(
						'owner_id' => -$this->_group_id,
						'message' => strip_tags(nl2br(html_entity_decode(strip_shortcodes($post->post_content)))),
						'from_group' => 1,
						'attachments'=>implode(",", $attachments)
				));
				if (!$postVK) die("wall.post");			
				
			}
			
			//-- прогрузим фотку в альбом
			if ( ($post->exportToAlbum>0) && ($hasPostThumb) ) {
				$vkUPServer=$this->invoke("photos.getUploadServer", array(
						"aid"=>$post->exportToAlbum,
						"gid"=>$this->_group_id,
						"save_big"=>1
				)); //TODO: А надо ли каждый раз? О_о
				if (!$vkUPServer) die("Problem with get upload album server");

				$thumbPath=get_attached_file(get_post_thumbnail_id($post->ID));
				$upFileData=$this->uploadFile($vkUPServer->upload_url, array('file1'=>"@{$thumbPath}"));
				if (!$upFileData) die("Problem with upload file 2");

				$saveFileData=$this->invoke("photos.save", array(
						"server"=>$upFileData->server,
						"photos_list"=>$upFileData->photos_list, //-- Доки говорят, что внутри может быть другой json
						"hash"=>$upFileData->hash,
						"aid"=>$post->exportToAlbum,
						"gid"=>$this->_group_id,
						"caption"=>$post->post_title,
						"description"=>strip_tags($post->post_content)
				));
				if (!$saveFileData) die("Problem with photos.save");

			}
			
			$wpdb->delete( $wpdb->vktemp, array("ID"=>$post->ID), array("%d") );
			
			update_post_meta($post->ID, 'vkPostID', $postVK->post_id);
			update_metadata('vk', $post->ID, 'postExportDT', $nowDT);
		}

		$export->step=$export->step+$limit;
		echo json_encode($export);
		die();
	}
 
	function publish_post($id, $attach=-1) {
		$post=get_post($id);

	}

	function getAllAlbums() {
		$albums=$this->invoke('photos.getAlbums', array(
				'oid' => -$this->_group_id
		));

		return $albums;
	}

	function category_form_fields($tag) {
		$exportToVK = get_metadata('vk', $tag->term_id, 'exportToVK', true);
		$exportToAlbum = get_metadata('vk', $tag->term_id, 'exportToAlbum', true);

		if (!$exportToVK) $exportToVK=false;
		if (!$exportToAlbum) $exportToAlbum=-1;		
		
		$albums=array();
		$albums=$this->getAllAlbums();		

		$albums[]=(object)array("aid"=>-1, "title"=> __("Without album") );
		$albums[]=(object)array("aid"=>-2, "title"=> __("Create new album") );
		
		$opt_albums='';
		foreach ($albums as $album) {
			$opt_albums.='<option value='.$album->aid.' '.(($album->aid==$exportToAlbum)? "selected": "" ).' >'.$album->title.'</option>';
		}


		echo '
			<tr class="form-field">
			<th scope="row" valign="top"><label for="cb_exporttovk">'.__("Export to VK?").'</label></th>
			<td>
			<input type="checkbox" style="width:10px; margin-right:5px;" name="cb_exporttovk" value="true" '.(($exportToVK)? "checked":"").' />'.__("Export").'<br>
			<p class="description">'.__("All post from this category will be export to VK.").'</p>
			</td>
			</tr>
					
			<tr class="form-field">
			<th scope="row" valign="top"><label for="lb_exporttoalbum">'.__("Export to album:").'</label></th>
			<td>
			<select name="lb_exporttoalbum">
			'.$opt_albums.'
			</select>
			<p class="description">'.__("All thumbs from post at this category will be export to select VK album.").'</p>
			</td>
			</tr>
		';

	}

	function edited_term_taxonomies($term_id) {		

		if (!$term_id) return;

		$this->refresh();		
		
		$exportToVK=(isset($_POST['cb_exporttovk']))? $_POST['cb_exporttovk'] : false;
		update_metadata('vk', $term_id, 'exportToVK', $exportToVK);		
		
		$exportToAlbum=(isset($_POST['lb_exporttoalbum']))? $_POST['lb_exporttoalbum'] : -1;
		
		if ($exportToAlbum==-2) { //-- создадим новый альбом
			$vkNewAlbum=$this->invoke("photos.createAlbum", array(
					'title'=>$_POST['name'],
					'group_id'=>$this->_group_id,
					'description'=>$_POST['description'],
					'comment_privacy'=>'0',
					'privacy'=>'0'
			));		
			
			if ($vkNewAlbum!==false) {
				$exportToAlbum=$vkNewAlbum->aid;
			} else $exportToAlbum=-1;
		}
		
		
		update_metadata('vk', $term_id, 'exportToAlbum', $exportToAlbum);
	}


	function showAdminMessages() {
		echo '<div class="vkerror"> ERROR <form > <input type="submit" name="captcha_need" value="Send" /> </form> </div>';
	}

}

$VKWallPost=new VKWallPost();

?>
