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
        
        // Test le type de compte afin de changer l'affichage
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
                $session->set('choixMois', $choixMois);
            } else {
                $choixMois = $request->get('choixMois');
                $session->set('choixMois', $choixMois);
            }
            $lesVisiteurs = $pdo->getLesVisiteursAValider($choixMois);
            return $this->render('GsbFraisBundle:GererFrais:gererfrais_listeMois_Visiteur.html.twig', array('typecompte' => $typeCompte, 'lesmois' => $lesMois, 'lesVisiteurs' => $lesVisiteurs, 'moisAselectionner' => $choixMois));
        } else {
            return $this->render('GsbFraisBundle:Home:connexion.html.twig');
        }
    }

    public function listefichefraisAction(Request $request) {

        $session = $request->getSession();
        $typeCompte = $session->get('typecompte');
        
        //Test si l'utilisateur est connecté et si il est bien Comptable
        if (estConnecte($request, $session) || $typeCompte == 2) {
            $pdo = $this->get('gsb_frais.pdo');
            $idVisiteur = $request->get('lstVisiteur');
            $session->set('choixVisiteur', $idVisiteur);
            $choixMois = $session->get('choixMois');
            $visiteur = $pdo->getLeVisiteur($idVisiteur);
            $nom = $visiteur['nom'];
            $prenom = $visiteur['prenom'];
            $puissanceVisiteur = $pdo->getForfaitKilometrique($idVisiteur);
            $typevehicule = $puissanceVisiteur['typevehicule'];
            $puissanceveheciule = $puissanceVisiteur['puissance'];
            $montantIndemniteKm = $puissanceVisiteur['montant'];
            $lesFraisHorsForfait = $pdo->getLesFraisHorsForfait($idVisiteur, $choixMois);
            $lesEtatsFrais = $pdo->getLesEtatFrais();
            $lesFraisForfait = $pdo->getLesFraisForfait($idVisiteur, $choixMois);
            $lesInfosFicheFrais = $pdo->getLesInfosFicheFrais($idVisiteur, $choixMois);
            $numAnnee = substr($choixMois, 0, 4);
            $numMois = substr($choixMois, 4, 2);
            $libetat = $lesInfosFicheFrais['libetat'];
            $totalHFValide = $pdo->getTotalHFValide($idVisiteur, $choixMois);
            $totalHFRefuse = $pdo->getTotalHFRefuse($idVisiteur, $choixMois);
            $totalForfait = $pdo->getTotalForfait($idVisiteur, $choixMois);
            $montantvalide = $lesInfosFicheFrais['montantvalide'];
            $nbjustificatifs = $lesInfosFicheFrais['nbjustificatifs'];
            $datemodif = $lesInfosFicheFrais['datemodif'];
            $datemodif = dateAnglaisVersFrancais($datemodif);
            $montantTotal = $pdo->getMontantTotal($idVisiteur, $choixMois);
            return $this->render('GsbFraisBundle:GererFrais:gererfrais_listeFraisAvalider.html.twig', array('lesetats' => $lesEtatsFrais,'montantKm' => $montantIndemniteKm,'totalForfait' => $totalForfait,'totalHFva' => $totalHFValide,'totalHFrf' => $totalHFRefuse, 'puissancevehicule' => $puissanceveheciule ,'typevehicule' => $typevehicule ,'montanttotal' => $montantTotal, 'datemodif' => $datemodif, 'nbjustificatifs' => $nbjustificatifs, 'montantvalide' => $montantvalide, 'libetat' => $libetat, 'nummois' => $numMois, 'numannee' => $numAnnee, 'lesinfosfichefrais' => $lesInfosFicheFrais, 'lesfraishorsforfait' => $lesFraisHorsForfait, 'lesfraisforfait' => $lesFraisForfait, 'nomvisiteur' => $nom, 'prenomvisiteur' => $prenom, 'typecompte' => $typeCompte));
        } else {
            return $this->render('GsbFraisBundle:Home:connexion.html.twig');
        }
    }

    public function majfraisAction(Request $request) {

        $pdo = $this->get('gsb_frais.pdo');
        $session = $request->getSession();
        $idVisiteur = $session->get('choixVisiteur');
        $choixMois = $session->get('choixMois');
        
        // Test si la requete est de type Ajax
        if ($request->isXmlHttpRequest()) {
            $id = $request->get('idFrais');
            $etat = $request->get('etat');
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
            $response = $this->forward('GsbFraisBundle:GererFrais:ListeFicheFrais',array('lstVisiteur' => $idVisiteur));
            return $response;
        }
    }

    public function paiementchoixMoisAction(Request $request) {
        $session = $request->getSession();
        $typeCompte = $session->get('typecompte');

        if (estConnecte($request, $session) || $typeCompte == 2) {
            $pdo = $this->get('gsb_frais.pdo');
            $lesMois = $pdo->getLesMoisAPayer();
            $lesCles = array_keys($lesMois);
            if(isset($lesCles[0])){
            $moisASelectionner = $lesCles[0];                
            }
            else{
                $moisASelectionner = null;
            }

            // Test si c'est de l'ajax pour afficher les Visiteurs
            if ($request->isXmlHttpRequest()) {
                $choixMois = $request->get('choixMois');
                $session->set('choixMois', $choixMois);
                $lesVisiteurs = $pdo->getLesVisiteursAPayer($choixMois);
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
            $lesVisiteurs = $pdo->getLesVisiteursAPayer($choixMois);
            return $this->render('GsbFraisBundle:GererFrais:gererfrais_listeMois_Visiteur_Paiement.html.twig', array('typecompte' => $typeCompte, 'lesmois' => $lesMois, 'lesVisiteurs' => $lesVisiteurs, 'moisAselectionner' => $choixMois));
        } else {
            return $this->render('GsbFraisBundle:Home:connexion.html.twig');
        }
    }

    public function paiementfichefraisAction(Request $request) {

        $session = $request->getSession();
        $typeCompte = $session->get('typecompte');
        $idVisiteur = $request->get('lstVisiteur');
        $session->set('choixVisiteur', $idVisiteur);
        $leMois = $session->get('choixMois');
        if (estConnecte($request, $session) || $typeCompte == 2 || $leMois != null || $idVisiteur != null) {


            $pdo = $this->get('gsb_frais.pdo');
            $visiteur = $pdo->getLeVisiteur($idVisiteur);
            $nom = $visiteur['nom'];
            $prenom = $visiteur['prenom'];
            $lesFraisHorsForfait = $pdo->getLesFraisHorsForfait($idVisiteur, $leMois);
            $montantTotal = $pdo->getMontantTotal($idVisiteur, $leMois);
            $lesFraisForfait = $pdo->getLesFraisForfait($idVisiteur, $leMois);
            $lesInfosFicheFrais = $pdo->getLesInfosFicheFrais($idVisiteur, $leMois);
            $numAnnee = substr($leMois, 0, 4);
            $numMois = substr($leMois, 4, 2);
            $libEtat = $lesInfosFicheFrais['libetat'];
            $montantValide = $lesInfosFicheFrais['montantvalide'];
            $nbJustificatifs = $lesInfosFicheFrais['nbjustificatifs'];
            $dateModif = $lesInfosFicheFrais['datemodif'];
            $dateModif = dateAnglaisVersFrancais($dateModif);
            return $this->render('GsbFraisBundle:GererFrais:gererfrais_suiviPaiement.html.twig', array('lesfraisforfait' => $lesFraisForfait, 'lesfraishorsforfait' => $lesFraisHorsForfait,
                        'lemois' => $leMois, 'numannee' => $numAnnee, 'nummois' => $numMois, 'libetat' => $libEtat,
                        'montantvalide' => $montantValide, 'nbjustificatifs' => $nbJustificatifs,
                        'datemodif' => $dateModif, 'montanttotal' => $montantTotal, 'typecompte' => $typeCompte, 'prenomvisiteur' => $prenom, 'nomvisiteur' => $nom));
        } else {
            return $this->render('GsbFraisBundle:Home:connexion.html.twig');
        }
    }

    public function miseEnPaiementfichefraisAction(Request $request) {
        
        $pdo = $this->get('gsb_frais.pdo');
        $session = $request->getSession();
        $idVisiteur = $session->get('choixVisiteur');
        $choixMois = $session->get('choixMois');
                $pdo->mettreEnPaiement($idVisiteur,$choixMois);
                $this->get('session')->getFlashBag()->add('info', 'La fiche à bien été mise en paiement');
        return $this->forward('GsbFraisBundle:GererFrais:paiementchoixMois');
    }

    public function ValidationfichefraisAction(Request $request) {
        
        $pdo = $this->get('gsb_frais.pdo');
        $session = $request->getSession();
        //$typeCompte = $session->get('typecompte');
        $idVisiteur = $session->get('choixVisiteur');
        $choixMois = $session->get('choixMois');
        $montantTotal = $pdo->getMontantTotal($idVisiteur, $choixMois);
        if ($pdo->verifEtatFraisHF($idVisiteur, $choixMois) == true) {
            $pdo->validerFicheFrais($idVisiteur, $choixMois, $montantTotal);
            $this->get('session')->getFlashBag()->add('info', 'La fiche à bien été validé');
            return $this->forward('GsbFraisBundle:GererFrais:index');
        } else {
            $this->get('session')->getFlashBag()->add('info','Des frais hors forfait sont encore EN ATTENTE');
            return $this->forward('GsbFraisBundle:GererFrais:listefichefrais',array('lstVisiteur' => $idVisiteur));

        }
    }

}
