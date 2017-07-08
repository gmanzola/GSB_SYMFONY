<?php

namespace GsbFraisBundle\Controller;

require_once("include/fct.inc.php");

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class HomeController extends Controller {

    public function indexAction(Request $request) {
        $session = $request->getSession();
        if (estConnecte($request, $session)) {
            return $this->render('GsbFraisBundle:accueil.html.twig');
        } else
            return $this->render('GsbFraisBundle:Home:connexion.html.twig');
    }

    public function validerconnexionAction(Request $request) {
        $session = $request->getSession();
        $login = $request->get('login');
        $mdp = $request->get('mdp');
        $pdo = $this->get('gsb_frais.pdo');
        $visiteur = $pdo->getInfosVisiteur($login, $mdp);
        if (isset($visiteur) AND ! is_array($visiteur)) {
            return $this->render('GsbFraisBundle:Home:connexion.html.twig', array(
                        'messages' => 'Erreur de login ou de mot de passe '));
        } else {
            $session->set('id', $visiteur['id']);
            $session->set('nom', $visiteur['nom']);
            $session->set('prenom', $visiteur['prenom']);
            $session->set('typecompte', $visiteur['typeCompte']);
        }


        switch ($visiteur['typeCompte']) {
            case 1: {
                    $route = 'gsb_frais_listefrais';
                    return $this->redirect($this->generateUrl($route));
                    // CHOIX DU REDIRECT CAR LE FORWARD NE CHANGE PAS L'URL ET L'AJAX NE FONCTIONNE PAS
                    
                    //$response = $this->forward('GsbFraisBundle:ListeFrais:index');
                    //return $response;
                }
            case 2: {
                    $route = 'gsb_frais_gererfrais';
                    return $this->redirect($this->generateUrl($route));
                    //$response = $this->forward('GsbFraisBundle:GererFrais:index');
                    //return $response;
                }
        }
    }

    public function deconnexionAction(Request $request) {
        $session = $request->getSession();
        $session->clear();
        return $this->render('GsbFraisBundle:Home:connexion.html.twig');
    }

}

?>
