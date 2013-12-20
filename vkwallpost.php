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

class VKWallPost {

	function __construct(){
		if (!is_admin()) return;
		//-- подгрузим язык --//
		define('TEXTDOMAIN', 'default');
		load_plugin_textdomain(TEXTDOMAIN, PLUGINDIR.'/'.dirname(plugin_basename(__FILE__)).'/langs');
			
		include 'setup.php';
		$setup=new SetupVKWP(__FILE__);


		add_action('init', array($this, 'init'));
		add_action('admin_menu', array(&$this, 'admin_menu'));
		add_action('wp_ajax_exportaction', array(&$this, 'ajax_exportaction'));
		add_action('publish_post', array(&$this, 'publish_post'), 1);

		add_action('edit_tag_form_fields', array($this, 'category_form_fields'), 10, 2);
		add_action('edit_category_form_fields', array($this, 'category_form_fields'), 10, 2);

		add_action('edited_term', array($this, 'edited_term_taxonomies'), 10, 2);

		add_action('admin_notices', array($this, 'showAdminMessages'), 10000);
		
			
	}

	function init() {
		global $wpdb;
		wp_localize_script( 'VKWallPost', 'VKWallPost', array());

		wp_register_script('VKWallPost', plugins_url('/vkwallpost.js', __FILE__ ), array('jquery'));

		//wp_register_script('SimpleModal',plugins_url('/simple-modal.js', __FILE__ ), array('jquery'));
		//wp_register_style('SimpleModal',plugins_url('/simple-modal.css', __FILE__ ));
		$wpdb->vkmeta = "{$wpdb->prefix}vkmeta";
	}



	function admin_menu() {
		$this->pagehook=add_menu_page( "VKWallPost", __('VKWallPost', TEXTDOMAIN), 5, 'VKWallPost',  array(&$this, 'menu_vkwallpost'));
		add_submenu_page("VKWallPost", __('Export', TEXTDOMAIN), __('Export', TEXTDOMAIN), 5, 'Export', array(&$this, 'menu_export'));
	}

	function menu_vkwallpost() {


	}

	function menu_export() {
		wp_enqueue_script("VKWallPost");
		wp_enqueue_script("SimpleModal");
		wp_enqueue_style("SimpleModal");
		$listTable=new VKWP__List_Table($this);
		$listTable->prepare_items($this->listPosts());
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
		<div class="percent">'.'0 '.__('of').' '.$listTable->total_items.'</div>
		<div class="bar" ></div>
		</div>
		</div>
		</div>
		<div>';
		$listTable->display();
		echo 	'</div>
		</div>
		';

		wp_localize_script( 'VKWallPost', 'vkmeta', array( 'total' => $listTable->total_items ));
	}

	function listPosts() {
		global $wpdb;

		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}vktemp;");
		$wpdb->query("
				INSERT INTO {$wpdb->prefix}vktemp (vk_id, exportToVK, exportToAlbum, ID, post_title, post_content)
				SELECT m.vk_id, m.meta_value as exportToVK, m2.meta_value as exportToAlbum, p.ID, p.post_title, p.post_content FROM `wp_vkmeta` as m
				LEFT JOIN `{$wpdb->prefix}term_relationships` as `rs` ON rs.term_taxonomy_id=m.vk_id
				LEFT JOIN `{$wpdb->prefix}vkmeta` as m2 ON m2.meta_key='exportToAlbum' and m2.meta_value>0 and  m2.vk_id=m.vk_id
				LEFT JOIN `{$wpdb->prefix}vkmeta` as m3 ON m3.meta_key='postExportDT' and  m3.vk_id=rs.object_id
				RIGHT JOIN `{$wpdb->prefix}posts` as p ON p.ID=rs.object_id and CAST(p.post_modified as DATE)>=CAST(IFNULL(m3.meta_value, '0000-00-00')  AS DATE)
				WHERE m.meta_key='exportToVK' and m.meta_value='true' ORDER BY p.post_date ASC
				");

		$result=array();
		$posts=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}vktemp");
		foreach ($posts as $post) {
			$res[]=array('id'=>$post->ID, 'title'=>$post->post_title, 'album'=>$post->exportToAlbum);
		}
		return $res;
	}

	function ajax_exportaction() {
		global $wpdb;
		$_export=urldecode($_GET['export']);
		$export  = json_decode($_export);
		$limit=1; //TODO: Добавить в настройки сколько за раз
		$posts=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}vktemp  LIMIT {$export->step},{$limit} ;");

		$nowDT = new DateTime();
		$nowDT=$nowDT->format('Y-m-d H:i:s');

		foreach ($posts as $post) {
				

			//-- прогрузим картинку для сообщения стены
			if (has_post_thumbnail($post->ID)) {
				$vkUPServer=Vkapi::invoke("photos.getWallUploadServer", array(
						//"aid"=>$post->exportToAlbum,
						"gid"=>"23914086",
						"save_big"=>1
				)); //TODO: А надо ли каждый раз? О_о
				if (!$vkUPServer) die("Problem with get upload server"); //-- ошибка с vkapi.php, пусть сам разбирается.


				$thumbPath=get_attached_file(get_post_thumbnail_id($post->ID));
				$upFileData=Vkapi::uploadFile($vkUPServer->upload_url, array('photo'=>"@{$thumbPath}"));
				if (!$upFileData) die("Problem with upload file");

				$saveFileData=Vkapi::invoke("photos.saveWallPhoto", array(
						"server"=>$upFileData->server,
						"photo"=>$upFileData->photo, //TODO: Доки говорят, что внутри может быть другой json
						"hash"=>$upFileData->hash,
						"gid"=>"23914086",
						"caption"=>$post->post_title
				));
				if (!$saveFileData) die("photos.saveWallPhoto");

				$attachments=$saveFileData[0]->id.",".get_term_link( (int)$post->vk_id, 'types');
			}
				
			//-- публикуем пост
			$postVK=VkApi::invoke('wall.post', array(
					'owner_id' => '-23914086',
					'message' => $post->post_title.$post->post_content,
					'from_group' => 1,
					'attachments'=>$attachments
			));
			if (!$postVK) die("wall.post");


			//-- прогрузим фотку в альбом
			if ($post->exportToAlbum>0) {
				$vkUPServer=Vkapi::invoke("photos.getUploadServer", array(
						"aid"=>$post->exportToAlbum,
						"gid"=>"23914086",
						"save_big"=>1
				)); //TODO: А надо ли каждый раз? О_о
				if (!$vkUPServer) die("Problem with get upload album server");

				$thumbPath=get_attached_file(get_post_thumbnail_id($post->ID));
				$upFileData=Vkapi::uploadFile($vkUPServer->upload_url, array('file1'=>"@{$thumbPath}"));
				if (!$upFileData) die("Problem with upload file");

				$saveFileData=Vkapi::invoke("photos.save", array(
						"server"=>$upFileData->server,
						"photos_list"=>$upFileData->photos_list, //TODO: Доки говорят, что внутри может быть другой json
						"hash"=>$upFileData->hash,
						"aid"=>$post->exportToAlbum,
						"gid"=>"23914086",
						"caption"=>$post->post_title,
						"description"=>$post->post_content
				));
				if (!$saveFileData) die("Problem with photos.save");

			}


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
		$albums=VkApi::invoke('photos.getAlbums', array(
				'oid' => '-23914086'
		));

		return $albums;
	}

	function category_form_fields($tag) {
		$exportToVK = get_metadata('vk', $tag->term_id, 'exportToVK', true);
		$exportToAlbum = get_metadata('vk', $tag->term_id, 'exportToAlbum', true);
		if (!$exportToVK) $exportToVK=false;
		if (!$exportToAlbum) $exportToAlbum=-1;

		$albums=$this->getAllAlbums();
		
		

		$opt_albums='';
		foreach ($albums as $album) {
			$opt_albums.='<option value='.$album->aid.' '.(($album->aid==$exportToAlbum)? "selected": "" ).' >'.$album->title.'</option>';
		}
		$opt_albums.='<option value="-1">'.__("Without album").'</option>';
		$opt_albums.='<option value="-2">'.__("Create new album").'</option>';

		echo '
		<tr class="form-field">
		<th scope="row" valign="top"><label for="cb_exporttovk">'.__("Export to VK?").'</label></th>
		<td>
		<input type="checkbox" style="width:10px; margin-right:5px;" name="cb_exporttovk" value="true" '.(($exportToVK)? "checked":$exportToVK).' />'.__("Export").'<br>
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
		$exportToVK=(isset($_POST['cb_exporttovk']))? $_POST['cb_exporttovk'] : false;
		update_metadata('vk', $term_id, 'exportToVK', $exportToVK);
		
		Vkapi::refresh(); 
		
		$exportToAlbum=(isset($_POST['lb_exporttoalbum']))? $_POST['lb_exporttoalbum'] : -1;
		update_metadata('vk', $term_id, 'exportToAlbum', $exportToAlbum);
	}


	function showAdminMessages() {
		
	}

}

$VKWallPost=new VKWallPost();

?>
