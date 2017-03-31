<?php

namespace GsbFraisBundle\Controller;

require_once("include/fct.inc.php");

//require_once ("include/class.pdogsb.inc.php");
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;

//use PdoGsb;
class ListeFraisController extends Controller {

    public function indexAction(Request $request) {

        $session = $request->getSession();
        $typeCompte = $session->get('typecompte');
        if (estConnecte($request, $session) || $typeCompte == 1) {

            $idVisiteur = $session->get('id');
            $pdo = $this->get('gsb_frais.pdo');
            $lesMois = $pdo->getLesMoisDisponibles($idVisiteur);
            $lesCles = array_keys($lesMois);
            $moisASelectionner = $lesCles[0];
            if ($request->isMethod('GET')) {
                // Afin de sélectionner par défaut le dernier mois dans la zone de liste
                // on demande toutes les clés, et on prend la première,
                // les mois étant triés décroissants
                $leMois = $moisASelectionner;
            } else {
                $leMois = $request->request->get('lstMois');
                if ($leMois == null) {
                    $leMois = $moisASelectionner;
                }
            }
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
            return $this->render('GsbFraisBundle:ListeFrais:listetouslesfrais.html.twig', array('lesmois' => $lesMois, 'lesfraisforfait' => $lesFraisForfait, 'lesfraishorsforfait' => $lesFraisHorsForfait,
                        'lemois' => $leMois, 'numannee' => $numAnnee, 'nummois' => $numMois, 'libetat' => $libEtat,
                        'montantvalide' => $montantValide, 'nbjustificatifs' => $nbJustificatifs,
                        'datemodif' => $dateModif, 'montanttotal' => $montantTotal, 'typecompte' => $typeCompte));
        } else {
            return $this->render('GsbFraisBundle:Home:connexion.html.twig');
        }
    }

}

?>
