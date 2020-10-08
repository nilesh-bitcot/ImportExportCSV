<?php
/**
 * 
 */
class NEO_THEME_ExportImportCSV {
	
	function __construct() {
		if ( isset( $_GET['action'] ) && $_GET['action'] == 'export_post_list' )  {
			// Handle CSV Export
			add_action( 'admin_init', array($this, 'csv_export') );
		}
		// Handle CSV Import
		add_action('wp_ajax_post_list_importer_ajax', array($this, 'csv_import'));
	}

	public function csv_export() {

	    if( !current_user_can( 'manage_options' ) ){ return false; }

	    // Check if we are in WP-Admin
	    if( !is_admin() ){ return false; }

	    // Nonce Check
	    $nonce = isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '';
	    if ( ! wp_verify_nonce( $nonce, 'download_csv' ) ) {
	        die( 'Security check error' );
	    }
	    
	    ob_start();

	    $delimiter = ",";
		$f = fopen('php://memory', 'w');
		$fields = array( 'Post Id', 'Post Title', 'Post Content' );
		fputcsv($f, $fields, $delimiter);

		$query_args = array( 'post_type'   => 'post', 'post_status' => 'publish' );

		$post_list = get_posts( $query_args );

		if( $post_list ) {
			foreach ( $post_list as $post ) {

		        $lineData = array( 
		        					$post->ID, 
		        					html_entity_decode( $post->post_title ), 
		        					html_entity_decode( $post->post_content )
		        				);
	        	
	        	fputcsv($f, $lineData, $delimiter);
		    }
		}
		

		$filename = "post_list_" . time() . ".csv";
		fseek($f, 0);
		/* output all remaining data on a file pointer */
		fpassthru($f);
	 
	    
	    header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
	    header( 'Content-Description: File Transfer' );
	    header( 'Content-type: text/csv' );
	    header( "Content-Disposition: attachment; filename={$filename}" );
	    header( 'Expires: 0' );
	    header( 'Pragma: public' );
	    
	    fclose( $f );
	    
	    ob_end_flush();
	    
	    die();
	}
	public function csv_import() {
		global $wpdb;

	    // Check for current user privileges 
	    if( !current_user_can( 'manage_options' ) ){ return false; }

	    // Check if we are in WP-Admin
	    if( !is_admin() ){ return false; }

	    // Nonce Check
	    $nonce = isset( $_POST['_wpnonce'] ) ? $_POST['_wpnonce'] : '';
	    if ( ! wp_verify_nonce( $nonce, 'upload_csv' ) ) {
	    	echo 'error';
	        die();
	    }
	    
	    $csv = array();
		
		if($_FILES['post_csv_import']['error'] == 0){
		    $name = $_FILES['post_csv_import']['name'];
		    $ext = strtolower(end(explode('.', $_FILES['post_csv_import']['name'])));
		    $type = $_FILES['post_csv_import']['type'];
		    $tmpName = $_FILES['post_csv_import']['tmp_name'];
		    
		    /* check the file is a csv */
		    if($ext === 'csv'){
		        if(($handle = fopen($tmpName, 'r')) !== FALSE) {
		            /* necessary if a large csv file */
		            set_time_limit(0);
		            $row = 0;
		            while(($data = fgetcsv($handle, 1000, ',')) !== FALSE) {                
		                $col_count = count($data);

		                if($row > 0){                
			                $post_id 		= intval( $data[0] );
			                $post_title 	= $data[1];
			                $post_content 	= $data[2];
			                
		                	if( !empty( $post_id ) ){
		                		// update post .

		                		$new_data = array(
									'ID' => $post_id,
									'post_title' 	=> $post_title,
									'post_content' => $post_content,
								);

								wp_update_post( $new_data );

		                	}else{
		                		// insert post 
		                		$new_post = array(
									'post_title' 	=> $post_title,
									'post_content' 	=> $post_content,
									'post_status' 	=> 'publish',
									'post_type' 	=> 'post'
								);
								wp_insert_post($new_post);
		                	}

			               
			            }
		                /* inc the row */
		                $row++;
		            }
		            fclose($handle);
		            echo "success";
		        }else{
		        	echo "error";
		        }
		    }else{
		    	echo "error";
			}

		}else{
			echo "error";
		}
	    
	    die();
	}
}
new NEO_THEME_ExportImportCSV;


class NEO_THEME_CustomAdminMenu {
	
	function __construct() {
		add_action('admin_menu', array($this, 'neo_theme_export_import_admin_menu'));	
	}

	public function neo_theme_export_import_admin_menu() {
	    add_menu_page('Import', 'Import Post', 'manage_options', 'import_post_csv', array($this, 'neo_theme_import_post_csv') );
	    add_submenu_page('import_post_csv', 'Export Posts', 'Export Posts', 'manage_options', 'export_post_csv', array($this, 'neo_theme_export_post_csv') );
	}

	public function neo_theme_import_post_csv() {
		echo '<div class="wrap">';
		echo '<h1>Import Post using CSV</h1>';
		?>
		<p><input type="hidden" name="import_wpnonce" id="import_wpnonce" value="<?php echo wp_create_nonce( 'upload_csv' ); ?>">
		</p>
		<center>
			<p><input type="file" name="post_csv_import" id="post_csv_import"></p>
			<p><a href="javascript:import_csv()" class="button button-primary"><span class="custom-label">Import CSV</span></a></p>
		</center>
<script>

	function import_csv(){				
		var fileExtension = ['csv'];
	    if ( jQuery.inArray( jQuery( '#post_csv_import' ).val().split( '.' ).pop().toLowerCase(), fileExtension) == -1 ) {
	    	alert( 'Only allowed : CSV file format!' );
	    }else{
	    	var file_data = jQuery( '#post_csv_import' ).prop( 'files' )[0];   
	    	var import_nonce = jQuery( '#import_wpnonce' ).val();
		    var form_data = new FormData();                  
		    form_data.append( 'post_csv_import', file_data );
		    form_data.append( '_wpnonce', import_nonce );
		    form_data.append( 'action', 'post_list_importer_ajax' );
		    /*console.log( form_data );*/
		    jQuery.ajax({
		        url: ajaxurl,
		        dataType: 'text',
		        cache: false,
		        contentType: false,
		        processData: false,
		        data: form_data,                         
		        type: 'post',
		        success: function(php_script_response){
		        	console.log(php_script_response);
		            if(php_script_response == 'error'){
		            	alert( 'Some error occurred please try again!' );
		            }else{
		            	alert( 'Post list import successfully!' );
		            }
		            
		            window.location.reload();
		        }
		     });
	    }
	}
	</script>
		<?php
		echo '</div>';
	}

	public function neo_theme_export_post_csv() {
		$params = array('action' => 'export_post_list', '_wpnonce' => wp_create_nonce( 'download_csv' ));
		$link = add_query_arg($params);
		echo '<div class="wrap">';
		echo '<h1>Export Posts using CSV</h1>';
		?>
		<center>
	    	<p><a href="<?php echo $link; ?>" class="button button-primary"><span class="custom-label">Export CSV</span></a></p>
	    </center>
		<?php
		echo '</div>';
	}
}

new NEO_THEME_CustomAdminMenu;



