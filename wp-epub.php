<?php
/*
Plugin Name: Category to ePub
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: Converts a Category to an ePub on post save, then adds said epub to media library
Version: The Plugin's Version Number, e.g.: 1.0
Author: Jack Reichert
Author URI: http://www.jackreichert.com
License: A "Slug" license name e.g. GPL2

Note: Doesn't deal with media (images) yet, currently links to online version.

*/
add_action( 'save_post', 'on_save_make_category_epub' );
// on savepost make epub
function on_save_make_category_epub( $post_id ) {

  //verify post is not a revision
	if ( !wp_is_post_revision( $post_id ) ) {
		
		// get post categories
		$post_categories = wp_get_post_categories( $post_id );
		// make epub of first category
		mkEpub($post_categories[0]);	
		
		
	}
	
}


 function mkEpub($cat_id){
 
 	// get cagetory info
 	$cat = get_term_by('id', $cat_id, 'category');

	 // get current media upload folder to create epub in
	 $upload_dir = wp_upload_dir();
		 
	// if doesn't exist create it
	 $epubPath = $upload_dir['path'];
	 if(!is_dir($epubPath))
	 	mkdir($epubPath,0777,true);
	 
	 // set epub filename
	 $fileName = $epubPath.'/'.wp_unique_filename( $epubPath, get_cat_name( $cat_id ) .'.epub' );
	 
	 // add mimetype uncompressed to epub
	 file_put_contents($fileName, base64_decode("UEsDBAoAAAAAAOmRAT1vYassFAAAABQAAAAIAAAAbWltZXR5cGVhcHBsaWNhdGlvbi9lcHViK3ppcFBLAQIUAAoAAAAAAOmRAT1vYassFAAAABQAAAAIAAAAAAAAAAAAIAAAAAAAAABtaW1ldHlwZVBLBQYAAAAAAQABADYAAAA6AAAAAAA="));
		
	 // new epub (commpressed) ZipArchive needs to be installed
	 $zip = new ZipArchive();
	 if (!$zip->open($fileName, ZIPARCHIVE::CREATE))
		 	return false;
	
	 // make META-INF dir
	 $zip->addEmptyDir('META-INF');

	 // create container.xml
	 $data = '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
	 $data .= '<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">'.PHP_EOL;
	 	$data .= "\t".'<rootfiles>'.PHP_EOL;
	 		$data .= "\t\t".'<rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>'.PHP_EOL;
	 	$data .= "\t".'</rootfiles>'.PHP_EOL;
	 $data .= '</container>';
	 $zip->addFromString('META-INF/container.xml', $data);
	
	 // create EPUB dir
	 $zip->addEmptyDir('EPUB');	
	 
	 // create Content dir
	 $zip->addEmptyDir('EPUB/Content');	
	 
	 // misc variables
	$opf_item = '';
	$itemref = '';
	$navPoint = '';
	$toc = '';	
	
	// get posts in categrory
	$cat_posts = get_posts("category=$cat_id");
	foreach($cat_posts as $i=>$cat_post){
			
		// set "chapter" filenames
		$fname = 'Chapter_'. (($i <= 9) 
				? '00'.$i 
				: (($i <= 99) 
					? '0'.$i 
					: $i)) .'.xhtml';
		
		// creates content files
		$data = '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
		$data .= '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">'.PHP_EOL;
		    $data .= "\t".'<head>'.PHP_EOL;
		        $data .= "\t\t".'<meta charset="utf-8" />'.PHP_EOL;
		        $data .= "\t\t".'<title>'.get_cat_name( $cat_id ).' | '.$cat_post->post_title.'</title>'.PHP_EOL;
		        $data .= "\t\t".'<link href="../Style/style.css" type="text/css" rel="stylesheet" />'.PHP_EOL;
		    $data .= "\t".'</head>'.PHP_EOL;
		    $data .= "\t".'<body>'.PHP_EOL;
		    $data .= '<h1>'.$cat_post->post_title.'</h1>'.PHP_EOL;
		    $data .= $cat_post->post_content;
		    $data .= "\t".'</body>'.PHP_EOL;
		$data .= '</html>'.PHP_EOL;

		$zip->addFromString('EPUB/Content/'.$fname, $data);
		
		// for package.opf
		$fileID = str_replace('.', '-', $fname);
		$opf_item .= "\t\t".'<item id="'.$fileID.'" href="Content/'.$fname.'" media-type="application/xhtml+xml"/>'.PHP_EOL;
		$itemref .= "\t\t".'<itemref idref="'.$fileID.'"/>'.PHP_EOL;
		
		// for toc.ncx
		$navPoint .= "\t\t".'<navPoint id="navpoint'.$i.'" playOrder="'.$i.'">'.PHP_EOL; 
			$navPoint .= "\t\t\t".'<navLabel>'.PHP_EOL; 
				$navPoint .= "\t\t\t\t".'<text>'.$cat_post->post_title.'</text>'.PHP_EOL; 
			$navPoint .= "\t\t\t".'</navLabel>'.PHP_EOL; 
			$navPoint .= "\t\t\t".'<content src="../Content/'.$fname.'"/>'.PHP_EOL; 
		$navPoint .= "\t\t".'</navPoint>'.PHP_EOL; 
		
		// for nav.xhtml
		$toc .= "\t\t\t\t".'<li><a href="../Content/'.$fname.'">'.$cat_post->post_title.'</a></li>'.PHP_EOL; 
						
	} 
	
	
	// create Style dir
	$zip->addEmptyDir('EPUB/Style');
	 // create style.css
	$data = 'p { margin-bottom: 1em; }';
	$zip->addFromString('EPUB/Style/style.css', $data);
	 	
	 // create package.opf
	$book_uid = str_replace(' ', '_', get_bloginfo('name')).'-'.$cat->slug;
	
	$data = '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
	$data .= '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="uid" xml:lang="en">'.PHP_EOL;
		$data .= "\t".'<metadata xmlns:dc="http://purl.org/dc/elements/1.1/">'.PHP_EOL;
			$data .= "\t\t".'<dc:identifier id="uid">'.$book_uid.'</dc:identifier>'.PHP_EOL;
			$data .= "\t\t".'<dc:title>'.$cat->name.'</dc:title>'.PHP_EOL;
			$data .= "\t\t".'<dc:creator>'.get_bloginfo('name').'</dc:creator>'.PHP_EOL;
			$data .= "\t\t".'<dc:language>en</dc:language>'.PHP_EOL;
			$data .= "\t\t".'<dc:date>'.date('Y-m-d').'</dc:date>'.PHP_EOL;
			$data .= "\t\t".'<dc:description>'.strip_tags($cat->description).'</dc:description>'.PHP_EOL;
			$data .= "\t\t".'<meta property="dcterms:modified">'.date('Y-m-d\TH:i:s\Z').'</meta>'.PHP_EOL;
			$data .= "\t\t".'<dc:rights>All rights reserved</dc:rights>'.PHP_EOL;
		$data .= "\t".'</metadata>'.PHP_EOL;
		$data .= "\t".'<manifest>'.PHP_EOL;
			$data .= "\t\t".'<item id="css" href="Style/style.css" media-type="text/css"/>'.PHP_EOL;
			$data .= "\t\t".'<item id="nav" href="Navigation/nav.xhtml" properties="nav" media-type="application/xhtml+xml"/>'.PHP_EOL;
			$data .= "\t\t".'<item id="ncx" href="Navigation/toc.ncx" media-type="application/x-dtbncx+xml" />'.PHP_EOL;
		$data .= $opf_item;
		$data .= "\t".'</manifest>'.PHP_EOL;
		$data .= "\t".'<spine toc="ncx">'.PHP_EOL;
			$data .= $itemref;
		$data .= "\t".'</spine>'.PHP_EOL;
	$data .= '</package>';
	
	$zip->addFromString('EPUB/package.opf', $data);

	// create Navigation dir
	 $zip->addEmptyDir('EPUB/Navigation');

	// create toc.ncx
	$data = '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
	$data .= '<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">'.PHP_EOL;
	$data .= "\t".'<head>'.PHP_EOL;
		$data .= "\t\t".'<meta name="dtb:uid" content="'.$book_uid.'"/>'.PHP_EOL;
		$data .= "\t\t".'<meta name="dtb:depth" content="1"/>'.PHP_EOL;
		$data .= "\t\t".'<meta name="dtb:totalPageCount" content="0"/>'.PHP_EOL;
		$data .= "\t\t".'<meta name="dtb:maxPageNumber" content="0"/>'.PHP_EOL;
	$data .= "\t".'</head>'.PHP_EOL;
	$data .= "\t".'<docTitle>'.PHP_EOL;
		$data .= "\t\t".'<text>'.$cat->name.'</text>'.PHP_EOL;
	$data .= "\t".'</docTitle>'.PHP_EOL;
	$data .= "\t".'<navMap>'.PHP_EOL;
	$data .= $navPoint;
	$data .= "\t".'</navMap>'.PHP_EOL;
	$data .= '</ncx>';
	
	$zip->addFromString('EPUB/Navigation/toc.ncx', $data);
	
	// create nav.xhtml
	$data = '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
	$data .= '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" lang="en" xml:lang="en">'.PHP_EOL;
	$data .= "\t".'<head>'.PHP_EOL;
		$data .= "\t\t".'<title>'.$cat->name.'</title>'.PHP_EOL;
		$data .= "\t\t".'<meta charset="utf-8" />'.PHP_EOL;
	$data .= "\t".'</head>'.PHP_EOL;
	$data .= "\t".'<body>'.PHP_EOL;
		$data .= "\t\t".'<section class="frontmatter TableOfContents" epub:type="frontmatter toc">'.PHP_EOL;
			$data .= "\t\t\t".'<header>'.PHP_EOL;
				$data .= "\t\t\t\t".'<h1>'.$cat->name.'</h1>'.PHP_EOL;
			$data .= "\t\t\t".'</header>'.PHP_EOL;
			$data .= "\t\t\t".'<nav xmlns:epub="http://www.idpf.org/2007/ops" epub:type="toc" id="toc">'.PHP_EOL;
				$data .= "\t\t\t\t".'<ol>'.PHP_EOL;
					$data .= $toc;
				$data .= "\t\t\t\t".'</ol>'.PHP_EOL;
			$data .= "\t\t\t".'</nav>'.PHP_EOL;
		$data .= "\t\t".'</section>'.PHP_EOL;
	$data .= "\t".'</body>'.PHP_EOL;
	$data .= '</html>';
	
	$zip->addFromString('EPUB/Navigation/nav.xhtml', $data);

	$zip->close();
	
	// attaches epub to media library
	$wp_filetype = 'application/epub+zip';
	$attachment = array(
		'guid' => $upload_dir['url'] . '/' . basename( $fileName ), 
		'post_mime_type' => $wp_filetype,
		'post_title' => preg_replace('/\.[^.]+$/', '', basename($fileName)),
		'post_content' => "This is an epub generated containing all posts from $cat->name category",
		'post_status' => 'published'
	);
	$attach_id = wp_insert_attachment( $attachment, $fileName );
	
	
 }
