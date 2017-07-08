<?php

namespace GsbFraisBundle\Controller;

require_once("include/fct.inc.php");

//require_once ("include/class.pdogsb.inc.php");
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

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
                $session->set('leMois', $leMois);
            } else {
                $leMois = $request->request->get('lstMois');
                if ($leMois == null) {
                    $leMois = $moisASelectionner;
                }
                $session->set('leMois', $leMois);
            }
            $lesFraisHorsForfait = $pdo->getLesFraisHorsForfait($idVisiteur, $leMois);
            $montantTotal = $pdo->getMontantTotal($idVisiteur, $leMois);
            $lesFraisForfait = $pdo->getLesFraisForfait($idVisiteur, $leMois);
            $lesInfosFicheFrais = $pdo->getLesInfosFicheFrais($idVisiteur, $leMois);
            $numAnnee = substr($leMois, 0, 4);
            $numMois = substr($leMois, 4, 2);
            $etatFiche = $lesInfosFicheFrais['idetat'];
            $libEtat = $lesInfosFicheFrais['libetat'];
            $montantValide = $lesInfosFicheFrais['montantvalide'];
            $nbJustificatifs = $lesInfosFicheFrais['nbjustificatifs'];
            $dateModif = $lesInfosFicheFrais['datemodif'];
            $dateModif = dateAnglaisVersFrancais($dateModif);
            return $this->render('GsbFraisBundle:ListeFrais:listetouslesfrais.html.twig', array('lesmois' => $lesMois, 'lesfraisforfait' => $lesFraisForfait, 'lesfraishorsforfait' => $lesFraisHorsForfait,
                        'lemois' => $leMois, 'numannee' => $numAnnee, 'nummois' => $numMois, 'libetat' => $libEtat,
                        'montantvalide' => $montantValide, 'nbjustificatifs' => $nbJustificatifs,
                        'datemodif' => $dateModif, 'montanttotal' => $montantTotal, 'typecompte' => $typeCompte
                        , 'etatfiche' => $etatFiche));
        } else {
            return $this->render('GsbFraisBundle:Home:connexion.html.twig');
        }
    }

    public function pdfAction(Request $request) {

        $session = $request->getSession();
        $idvisiteur = $session->get('id');
        $nomvisiteur = $session->get('nom');
        $prenomvisiteur = $session->get('prenom');
        $moisTotal = $session->get('leMois');
        $mois = substr($moisTotal, 4, 2);
        $annee = substr($moisTotal, 0, 4);
        $pdo = $this->get('gsb_frais.pdo');
        $elementForfait = $pdo->getLesFraisForfaitPdf($idvisiteur, $moisTotal);
        $horsforfait = $pdo->getLesFraisHorsForfait($idvisiteur, $moisTotal);
        $totalFrais = $pdo->getTotalForfaitPdf($idvisiteur, $moisTotal);

        ob_get_clean();
// Activation de la classe
        global $pdf;
        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(0);
        $pdf->Image('https://kvinet44.files.wordpress.com/2015/06/gsb-31.png', 70, 5, 60, 0, 'PNG');
        $pdf->Ln(20);
        $pdf->Text(8, 50, 'Visiteur : ' . $prenomvisiteur . ' ' . $nomvisiteur);
        $pdf->Text(8, 55, 'Mois : ' . $mois . "/" . $annee);
// Position de l'entête à 10mm des infos (69+10)
        $position_entete = 79;

        function entete_table($position_entete) {
            global $pdf;
            $pdf->SetDrawColor(183); // Couleur du fond
            $pdf->SetFillColor(221); // Couleur des filets
            $pdf->SetTextColor(0); // Couleur du texte
            $pdf->SetY($position_entete);
            $pdf->SetX(8); //position de la premiere colonne
            $pdf->Cell(40, 8, 'Frais Forfaitaires', 1, 0, 'L', 1);
            $pdf->SetX(48); //40+8
            $pdf->Cell(40, 8, 'Quantite', 1, 0, 'C', 1);
            $pdf->SetX(88);
            $pdf->Cell(40, 8, 'Montant unitaire', 1, 0, 'C', 1);
            $pdf->SetX(128);
            $pdf->Cell(35, 8, 'Total', 1, 0, 'C', 1);
            $pdf->Ln(); // Retour à la ligne
        }
        $pdf->Text(87, 70, 'Frais forfaits');
        entete_table($position_entete);
//liste des détails
        $position_detail = 87;
        foreach ($elementForfait as $unElement) {
            $pdf->SetY($position_detail);
            $pdf->SetX(8);
            $pdf->MultiCell(40, 8, utf8_decode($unElement['libelle']), 1, 'L');
            $pdf->SetY($position_detail);
            $pdf->SetX(48);
            $pdf->MultiCell(40, 8, $unElement['quantite'], 1, 'C');
            $pdf->SetY($position_detail);
            $pdf->SetX(88);
            $pdf->MultiCell(40, 8, $unElement['montant'], 1, 'R');
            $pdf->SetY($position_detail);
            $pdf->SetX(128);
            $pdf->MultiCell(35, 8, $unElement['total'], 1, 'R');
            $position_detail += 8;
        }
//liste details hors forfaits
        $position_detail2 = $position_detail + 10;
        $pdf->Text(85, $position_detail2, 'Autres Frais');
        $position_entete2 = $position_detail2 + 10;

        function entete_table2($position_entete2) {
            global $pdf;
            $pdf->SetDrawColor(183); // Couleur du fond
            $pdf->SetFillColor(221); // Couleur des filets
            $pdf->SetTextColor(0); // Couleur du texte
            $pdf->SetY($position_entete2);
            $pdf->SetX(8); //position de la premiere colonne
            $pdf->Cell(40, 8, 'Date', 1, 0, 'L', 1);
            $pdf->SetX(48); //40+8
            $pdf->Cell(80, 8, 'Libelle', 1, 0, 'C', 1);
            $pdf->SetX(128);
            $pdf->Cell(35, 8, 'Montant', 1, 0, 'R', 1);
            $pdf->Ln(); // Retour à la ligne
        }

        entete_table2($position_entete2);
        $position_detail2 = $position_entete2 + 8;
        foreach ($horsforfait as $unElement) {
            $pdf->SetY($position_detail2);
            $pdf->SetX(8);
            $pdf->MultiCell(40, 8, utf8_decode($unElement['date']), 1, 'L');
            $pdf->SetY($position_detail2);
            $pdf->SetX(48);
            if ($unElement['etat'] == 'rf') {
                $pdf->MultiCell(80, 8, utf8_decode("« REFUSE » " . $unElement['libelle']), 1, 'L');
            } else {
                $pdf->MultiCell(80, 8, utf8_decode($unElement['libelle']), 1, 'L');
            }
            $pdf->SetY($position_detail2);
            $pdf->SetX(128);
            $pdf->MultiCell(35, 8, $unElement['montant'], 1, 'R');
            $position_detail2 += 8;
        }
        foreach ($totalFrais as $total) {
            $pdf->SetX(100);
            $pdf->Cell(28, 8, 'Total : ', 1, 'L');
            $pdf->SetX(128);
            $pdf->Cell(35, 8, $total['total'], 1, 'R');
        }

        $pdf->SetY(-30);
            $pdf->Cell(190, 5, utf8_decode('Fait à Paris, le ' . date("d-m-Y")));
            $pdf->SetY(-35);
            $pdf->Cell(202, 5, utf8_decode('Vu l\'agent comptable'));
            $pdf->Image('https://upload.wikimedia.org/wikipedia/commons/thumb/5/56/Autograph_of_Benjamin_Franklin.svg/220px-Autograph_of_Benjamin_Franklin.svg.png', 110, 250, 60);

        return new Response($pdf->Output(), 200, array(
            'Content-Type' => 'application/pdf'));
    }

}

?>
