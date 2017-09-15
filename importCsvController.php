<?php

namespace Drupal\custompage\Controller;
 
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\node\Entity\Node; 
use \Drupal\file\Entity\File;
use \Drupal\field_collection\Entity\FieldCollectionItem;


class importCsvController extends ControllerBase {

 
	public function content() {
		return array(
	       	'#theme' => 'upload',
    	);
	}

 
	/** **********************************************************************
	* Creation de l'url du fichier
	*
	* @param {string} $name - Nom de l'image
	* @param {string} $dir - Nom du repertoire
	*
	* @return {string} url du fichier
	*
	*/
	public function getFile($name, $dir) {
		$content = file_get_contents($_SERVER["DOCUMENT_ROOT"] . '/sites/default/files/uploads/'. $name);
		$file = file_save_data($content, "public://". $name ."", FILE_EXISTS_REPLACE);
		return $file;
	}



	/** **********************************************************************
	* Creation d'un tableau contenant tous les fichiers du meme nom que l'image renseignée
	*
	* @param {string} $famille - Nom de la famille, utilisé pour le chemin du repertoire
	* @param {string} $produit - Nom du produit, utilisé pour le chemin du repertoire
	* @param {string} $searchword - Nom de l'image ou du pdf à chercher dans le repertoire
	* @param {booleen} $pdf - return true si le fichier recherché est un PDF
	*
	* @return {array} Tableau des résultats
	*
	*/
	public function arrayAllFiles($famille, $produit, $searchword, $pdf) {

		// Creation d'un tableau contenant tous les fichiers du repertoire
		$dir = $_SERVER["DOCUMENT_ROOT"] . '/sites/default/files/uploads/'.$famille.'/'.$produit;
		$array_dir = array();
		if (is_dir($dir)) {
			if ($dh = opendir($dir)) {
				while (($file = readdir($dh)) !== false) {
					array_push($array_dir, $file);
				}
				closedir($dh);
			}
		}
 
		// Recherche et création d'une liste sous forme de tableau de tous les fichiers contenant le nom du PDF ou de l'image
		$searchword = $searchword;
		$array_img = array();

		foreach($array_dir as $key => $value) {
			if ($pdf){
				if(preg_match("/\b$searchword\b/i", $value) && preg_match("/\b.pdf\b/i", $value)) {
					$array_img[$key] = $value;
				}
			} else {
				if(preg_match("/\b$searchword\b/i", $value)) {
					$array_img[$key] = $value;
				}
			}	 
		}	 
		
		$arrayStructuredFiles = $this->arrayStructuredFiles($array_img, $produit, $famille);
		return $arrayStructuredFiles;
	}

	


	/** **********************************************************************
	* Création du tableau structuré spécialement pour renseigné le champ d'image illimité
	*
	* @param {array} $array_img - Tableau qui contient toutes les images non structuré
	* @param {string} $produit - Nom du produit
	* @param {string} $famille - Nom de la famillee
	*
	* @return {string} Tableau structuré et adapté au format attendu du champ
	*
	*/
	public function arrayStructuredFiles($array_img, $produit, $famille) {	
		// Creation du tableau contenant les ID de toutes les images
		$array_slider = array();
		$count = 0;
		foreach ($array_img as $key => $valueImage) {
			$content_slider = file_get_contents($_SERVER["DOCUMENT_ROOT"] . '/sites/default/files/uploads/'.$famille.'/'.$produit.'/'. $valueImage);
			$file_slider = file_save_data($content_slider, "public://". $valueImage ."", FILE_EXISTS_REPLACE);
			
			$array_slider[$count] = ['target_id' => $file_slider->id(), 'alt' => $produit];
			$count++;
		} 
		return $array_slider;
	}

	
	/** **********************************************************************
	* Requete pour obtenir l'ID des designers par leurs noms
	*
	* @param {string} $names - Nom des designers
	*
	* @return {array} $dids_array - ID des designers
	*
	*/
	public function getDesignID($names) {	
		
		$designers = explode(",", $names);  
		$design_array  = array();
		
		
		// Suppression des espaces vides devant les noms 
		foreach ($designers as $key => $value) {
			$trimmed = trim($value);
			$trimmed = str_replace('-', ' ', $trimmed); 
			array_push($design_array, $trimmed);  
		}
		
		
		// Création du tableau contenant les ID des designers
		$dids_array = array();
		foreach ($design_array as $key => $value) {
			$query = \Drupal::entityQuery('node')
			->condition('type', 'designer')          
			->condition('status', 1)
			->condition('title', $value, 'CONTAINS');
			$nids = $query->execute(); 
			array_push($dids_array, reset($nids));  
		}
		 
		return $dids_array;
	}
	 


	/** **********************************************************************
	* Création d'un tableau contenant les TID des matériaux à partir de leurs noms
	*
	* @param {string} $termes - termes de taxo
	*
	* @return {array} $tid_array - TID Des termes de taxo
	*
	*/
	public function getMateriauxTID($termes) {	
		
		$termes = explode(",", $termes);  
		$taxo_array  = array();
		
		// Creation du tableau 
		foreach ($termes as $key => $value) {
			$trimmed = trim($value);

			$tid = \Drupal::entityTypeManager()
			->getStorage('taxonomy_term')
			->loadByProperties(['name' => $trimmed]);
 
			if ( is_int(key($tid)) ) {
				array_push($taxo_array, key($tid));  
			} else {
				array_push($taxo_array, '+');  
			}
		}
		array_push($taxo_array, '+');  

		 
		return $taxo_array;
	}


	/** **********************************************************************
	* Récuperation et Parse du CSV et création des nodes
	*
	* @param {file} $request - Fichier CSV uploadé
	*
	*/
	public function getCsv(Request $request) {

		// Recuperation du fichiers CSV
		$name = $_FILES['myfile']['tmp_name'];
		$destination = $_SERVER["DOCUMENT_ROOT"] . '/sites/default/files/uploads/' . $_FILES['myfile']['name'];


		// Enregistrement du fichier CSV dans le dossier upload
		if( move_uploaded_file($name, $destination) ){
			move_uploaded_file($name, $destination);
		} else {
			var_dump('Erreur...');die;
		}

		// Ouverture et Parse du fichier CSV -> array
		$arrayCSV = null;
		if (($handle = fopen($destination, "r")) !== FALSE) {
			while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
				$arrayCSV[] = $data;
			}
			fclose($handle);
		}


		// Parcours du tableau pour récuperer chaque champ
		foreach ($arrayCSV as $key => $arrayProduits) {
			
			// Title de la page
			$produit = $arrayProduits[1];


			// Test si le produit existe déjà
			$query = \Drupal::entityQuery('node')
			->condition('type', 'produit')
			->condition('title', $produit, '=');         
			$result = $query->execute(); 
			$nid = reset($result); 
		 

		// Si le produit n'existe pas
		if (!$result) {
				
				// $Variable
				$famille = $arrayProduits[0]; 
				$img_slider = $this->arrayAllFiles($famille, $produit, $arrayProduits[3], false); 
				$plan = $this->arrayAllFiles($famille, $produit, $arrayProduits[4], false); 
				$fiche_technique = $this->arrayAllFiles($famille, $produit, $arrayProduits[5], true); 
				$thumbnail = $img_slider[0]; 
				$designer = $this->getDesignID($arrayProduits[6]); 
				$description = $arrayProduits[7]; 
				$materiaux = $this->getMateriauxTID($arrayProduits[8]); 
				$description_materiaux = $arrayProduits[9]; 
				$declinaison_code = $arrayProduits[10]; 
				$declinaison_designation = $arrayProduits[11]; 
				$declinaison_tarif = $arrayProduits[12]; 
				$collection = $arrayProduits[13]; 
				$produit_asso1 = $arrayProduits[14];
				$produit_asso2 = $arrayProduits[15];
				$produit_asso3 = $arrayProduits[16];
				$produit_asso4 = $arrayProduits[17];
				$produit_asso5 = $arrayProduits[18];
					
				
				
				// ********************** TAXONOMIES ********************** //
				// famille
				$tid_famille = \Drupal::entityTypeManager()
					->getStorage('taxonomy_term')
					->loadByProperties(['name' => $famille]);
				// collection
				$tid_collection = \Drupal::entityTypeManager()
					->getStorage('taxonomy_term')
					->loadByProperties(['name' => $collection]);


				// ********************** CREATE NODE ********************** // 
				$node = Node::create([
					'type' => 'produit',
					'title' => $produit,
					'field_famille_produit' => $tid_famille,
					'field_collection_produit' => $tid_collection,
					'field_slide_produit' => $img_slider,
					'field_thumbnail_produit' => $thumbnail,
					'field_visuel_dimension_prod' => $plan,
					'field_pdf_fiche_technique' => $fiche_technique,
					'field_designer_produit' => $designer,
					'field_contenu_description_prod' => $description, 
					'field_description_materiaux' => $description_materiaux, 
					'field_produit_associe_1' => $produit_asso1, 
					'field_produit_associe_2' => $produit_asso2, 
					'field_produit_associe_3' => $produit_asso3, 
					'field_produit_associe_4' => $produit_asso4, 
					'field_produit_associe_6' => $produit_asso5, 
					//    'field_ordre_produit' => $ordre,
				]);  
				$node->save();

				
				// ********************** CREATE COLLECTION MATERIAUX ********************** // 
				$array_vide = [];
				foreach ($materiaux as $value) {
					if ( is_int($value) ) {
						$array_vide[] = $value; 
					} else {					
						$field_collection_item = FieldCollectionItem::create(['field_name' => 'field_materiaux_taxo']);
						$field_collection_item->field_termes_materiaux->setValue($array_vide);
						$field_collection_item->setHostEntity($node); 
						$field_collection_item->save();
					
						$array_vide = array();
					}
				}


				// ********************** CREATE COLLECTION DECLINAISONS ********************** // 
				$field_collection_item = FieldCollectionItem::create(['field_name' => 'field_declinaisons_produit']);
				$field_collection_item->field_code_declinaison_produit->setValue($declinaison_code);
				$field_collection_item->field_designation_decl_produit->setValue($declinaison_designation);
				$field_collection_item->field_tarif_ht_decl_produit->setValue($declinaison_tarif);
				$field_collection_item->setHostEntity($node); 
				$field_collection_item->save();



				// Si le produit existe	
			} else {
				
				// $Variables
				$declinaison_code = $arrayProduits[10]; 
				$declinaison_designation = $arrayProduits[11];
				$declinaison_tarif = $arrayProduits[12]; 

				$node_update = Node::load($nid); 
				

				// ********************** CREATE COLLECTION DECLINAISONS ********************** // 
				
				$field_collection_item_update = FieldCollectionItem::create(['field_name' => 'field_declinaisons_produit']);
				$field_collection_item_update->field_code_declinaison_produit->setValue($declinaison_code);
				$field_collection_item_update->field_designation_decl_produit->setValue($declinaison_designation);
				$field_collection_item_update->field_tarif_ht_decl_produit->setValue($declinaison_tarif);
				$field_collection_item_update->setHostEntity($node_update); 
				$field_collection_item_update->save();
			
			}
			

		}			 	

		$return = array(
			'query' => $arrayProduits,
		);

		return new JsonResponse($return);
	}

}
 

?>

