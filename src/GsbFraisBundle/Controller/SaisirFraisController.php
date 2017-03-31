<?php

namespace GsbFraisBundle\Controller;

require_once("include/fct.inc.php");

//require_once ("include/class.pdogsb.inc.php");
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

//use PdoGsb;
class SaisirFraisController extends Controller {

    public function indexAction(Request $request) {
        $session = $request->getSession();
        $typeCompte = $session->get('typecompte');

        if (estConnecte($request, $session) || $typeCompte == 1) {
             
           //$choixPuissance = $session->get('choixPuissance');
            $idVisiteur = $session->get('id');
            $mois = getMois(date("d/m/Y"));
            $numAnnee = substr($mois, 0, 4);
            $numMois = substr($mois, 4, 2);
            
            if ($pdo->estPremierFraisMois($idVisiteur, $mois)) {
                $pdo->creeNouvellesLignesFrais($idVisiteur, $mois);
            }
            $lesErreursForfaits = array();
            if ($request->isMethod('POST')) {
                $lesFrais = $request->get('lesFrais');
                if (lesQteFraisValides($lesFrais)) {
                    $pdo->majFraisForfait($idVisiteur, $mois, $lesFrais);
                    //
                } else {
                    $lesErreursForfaits[] = "Les valeurs des frais doivent être numériques";
                }
            }
            //$lesPuissances = $pdo->getLesPuissances();
            // Afin de sélectionner la puissance saisir on la recupere via requete
            $puissanceVisiteur = $pdo->getForfaitKilometrique($idVisiteur);
            $lesFraisForfait = $pdo->getLesFraisForfait($idVisiteur, $mois);
            $lesFraisHorsForfait = $pdo->getLesFraisHorsForfait($idVisiteur, $mois);
            return $this->render('GsbFraisBundle:SaisirFrais:saisirtouslesfrais.html.twig', array('typecompte' => $typeCompte, 'lesfraisforfait' => $lesFraisForfait, 'lesfraishorsforfait' => $lesFraisHorsForfait, 'nummois' => $numMois,
                        'numannee' => $numAnnee, 'leserreursforfait' => $lesErreursForfaits, 'leserreurshorsforfait' => null, 'puissanceVisiteur' => $puissanceVisiteur));
        } else {
            return $this->render('GsbFraisBundle:Home:connexion.html.twig');
        }
    }

    public function validerfraishorsforfaitAction(Request $request) {
        $session = $request->getSession();
        $typeCompte = $session->get('typecompte');
        if (estConnecte($request, $session) || $typeCompte == 1) {
            
            $pdo = $this->get('gsb_frais.pdo');
            $idVisiteur = $session->get('id');
            $mois = getMois(date("d/m/Y"));
            $numAnnee = substr($mois, 0, 4);
            $numMois = substr($mois, 4, 2);

            $dateFrais = $request->get('dateFrais');
            $libelle = $request->get('libelle');
            $montant = $request->get('montant');
            $lesErreursHorsForfait = valideInfosFrais($dateFrais, $libelle, $montant);
            if (count($lesErreursHorsForfait) == 0) {
                $pdo->creeNouveauFraisHorsForfait($idVisiteur, $mois, $libelle, $dateFrais, $montant);
            }
            //$lesPuissances = $pdo->getLesPuissances();
            // Afin de sélectionner la puissance saisir on la recupere via requete
            $puissanceVisiteur = $pdo->getForfaitKilometrique($idVisiteur, $mois);
            $lesFraisForfait = $pdo->getLesFraisForfait($idVisiteur, $mois);
            $lesFraisHorsForfait = $pdo->getLesFraisHorsForfait($idVisiteur, $mois);
            return $this->render('GsbFraisBundle:SaisirFrais:saisirtouslesfrais.html.twig', array('typecompte' => $typeCompte, 'lesfraisforfait' => $lesFraisForfait, 'lesfraishorsforfait' => $lesFraisHorsForfait, 'nummois' => $numMois,
                        'numannee' => $numAnnee, 'leserreursforfait' => null, 'leserreurshorsforfait' => $lesErreursHorsForfait, 'puissanceVisiteur' => $puissanceVisiteur));
        } else {
            return $this->render('GsbFraisBundle:Home:connexion.html.twig');
        }
    }

    public function supprimerfraishorsforfaitAction(Request $request, $id) {
        $session = $request->getSession();
        $typeCompte = $session->get('typecompte');

        if (estConnecte($request, $session) || $typeCompte == 1) {
            $pdo = $this->get('gsb_frais.pdo');
            $idVisiteur = $session->get('id');
            $mois = getMois(date("d/m/Y"));
            $numAnnee = substr($mois, 0, 4);
            $numMois = substr($mois, 4, 2);


            if ($pdo->estValideSuppressionFrais($idVisiteur, $mois, $id))
                $pdo->supprimerFraisHorsForfait($id);
            else {
                $response = new Response;
                $response->setContent("<h2>Page introuvable erreur 404 ");
                $response->setStatusCode(404);
                return $response;
            }
            //$lesPuissances = $pdo->getLesPuissances();
            // Afin de sélectionner la puissance saisir on la recupere via requete
            $puissanceVisiteur = $pdo->getForfaitKilometrique($idVisiteur, $mois);
            $lesFraisForfait = $pdo->getLesFraisForfait($idVisiteur, $mois);
            $lesFraisHorsForfait = $pdo->getLesFraisHorsForfait($idVisiteur, $mois);
            return $this->render('GsbFraisBundle:SaisirFrais:saisirtouslesfrais.html.twig', array('typecompte' => $typeCompte, 'lesfraisforfait' => $lesFraisForfait, 'lesfraishorsforfait' => $lesFraisHorsForfait, 'nummois' => $numMois,
                        'numannee' => $numAnnee, 'leserreursforfait' => null, 'leserreurshorsforfait' => null, 'puissanceVisiteur' => $puissanceVisiteur));
        } else {
            return $this->render('GsbFraisBundle:Home:connexion.html.twig');
        }
    }

}
