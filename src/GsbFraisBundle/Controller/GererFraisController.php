<?php

namespace GsbFraisBundle\Controller;

require_once("include/fct.inc.php");

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class GererFraisController extends Controller {

    public function indexAction(Request $request) {

        $session = $request->getSession();
        $typeCompte = $session->get('typecompte');

        if (estConnecte($request, $session) || $typeCompte == 2) {
            $pdo = $this->get('gsb_frais.pdo');
            $lesMois = $pdo->getLesMoisAvalider();
            $lesCles = array_keys($lesMois);
            $moisASelectionner = $lesCles[0];
            // Test si c'est de l'ajax pour afficher les Visiteurs
            if ($request->isXmlHttpRequest()) {
                $choixMois = $request->get('choixMois');
                $session->set('choixMois', $choixMois);
                $lesVisiteurs = $pdo->getLesVisiteursAValider($choixMois);
                return new JsonResponse($lesVisiteurs);
            }
            if ($request->isMethod('GET')) {
                // Afin de sélectionner par défaut le dernier mois dans la zone de liste
                // on demande toutes les clés, et on prend la première,
                // les mois étant triés décroissants
                $choixMois = $moisASelectionner;
            } else {
                $choixMois = $request->get('choixMois');
                $session->set('choixMois', $choixMois);
            }
            //$lesVisiteurs = $pdo->getLesVisiteursAValider($choixMois);
            $lesVisiteurs = $pdo->getLesVisiteursAValider($choixMois);
            return $this->render('GsbFraisBundle:GererFrais:gererfrais_listeMois_Visiteur.html.twig', array('typecompte' => $typeCompte, 'lesmois' => $lesMois, 'lesVisiteurs' => $lesVisiteurs, 'moisAselectionner' => $choixMois));
        } else {
            return $this->render('GsbFraisBundle:Home:connexion.html.twig');
        }
    }

    public function listefichefraisAction(Request $request) {

        $session = $request->getSession();
        $typeCompte = $session->get('typecompte');

        if (estConnecte($request, $session) || $typeCompte == 2) {
            $pdo = $this->get('gsb_frais.pdo');
            $idVisiteur = $session->get('choixVisiteur');
            $session->set('choixVisiteur', $idVisiteur);
            $choixMois = $session->get('choixMois');
            $visiteur = $pdo->getLeVisiteur($idVisiteur);
            $nom = $visiteur['nom'];
            $prenom = $visiteur['prenom'];
            $lesFraisHorsForfait = $pdo->getLesFraisHorsForfait($idVisiteur, $choixMois);
            $lesFraisForfait = $pdo->getLesFraisForfait($idVisiteur, $choixMois);
            $lesInfosFicheFrais = $pdo->getLesInfosFicheFrais($idVisiteur, $choixMois);
            $numAnnee = substr($choixMois, 0, 4);
            $numMois = substr($choixMois, 4, 2);
            $libetat = $lesInfosFicheFrais['libetat'];
            $montantvalide = $lesInfosFicheFrais['montantvalide'];
            $nbjustificatifs = $lesInfosFicheFrais['nbjustificatifs'];
            $datemodif = $lesInfosFicheFrais['datemodif'];
            $datemodif = dateAnglaisVersFrancais($datemodif);
            $montantTotal = $pdo->getMontantTotal($idVisiteur, $choixMois);

            return $this->render('GsbFraisBundle:GererFrais:gererfrais_listeFraisAvalider.html.twig', array('montanttotal' => $montantTotal, 'datemodif' => $datemodif, 'nbjustificatifs' => $nbjustificatifs, 'montantvalide' => $montantvalide, 'libetat' => $libetat, 'nummois' => $numMois, 'numannee' => $numAnnee, 'lesinfosfichefrais' => $lesInfosFicheFrais, 'lesfraishorsforfait' => $lesFraisHorsForfait, 'lesfraisforfait' => $lesFraisForfait, 'nomvisiteur' => $nom, 'prenomvisiteur' => $prenom, 'typecompte' => $typeCompte));
        } else {
            return $this->render('GsbFraisBundle:Home:connexion.html.twig');
        }
    }

    public function majfraisAction(Request $request) {

        $pdo = $this->get('gsb_frais.pdo');
        $session = $request->getSession();
        $idVisiteur = $session->get('choixVisiteur');
        $choixMois = $session->get('choixMois');

        if ($request->isXmlHttpRequest()) {
            $id = $request->get('idFrais');
            $etat = $request->get('etat');
            var_dump($etat);
            if ($etat != 'rp') {
                $pdo->setEtatFraisHorsForfait($id, $etat);
            } else {
                $moisSuivant = getMoisNext($numAnnee, substr($choixMois, 4, 2)); // appel de la fonction qui ajoute 1 au mois 
                if ($pdo->estPremierFraisMois($idVisiteur, $moisSuivant) == true) {
                    $pdo->creeNouvellesLignesFrais($idVisiteur, $moisSuivant);
                    $pdo->ReportFraisHorsForfait($moisSuivant, $idVisiteur, $id);
                } else {
                    $pdo->ReportFraisHorsForfait($moisSuivant, $idVisiteur, $id);
                }
            }
        }

        $lesErreursForfaits = array();
        if ($request->isMethod('POST')) {
            $lesFrais = $request->get('lesFrais');
            if (lesQteFraisValides($lesFrais)) {
                $pdo->majFraisForfait($idVisiteur, $choixMois, $lesFrais);
                //
            } else {
                $lesErreursForfaits[] = "Les valeurs des frais doivent être numériques";
            }
            $response = $this->forward('GsbFraisBundle:GererFrais:ListeFicheFrais');
            return $response;
        }
    }

}
