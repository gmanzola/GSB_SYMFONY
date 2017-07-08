<?php

namespace GsbFraisBundle\services;

use PDO;

class PdoGsb {

    private static $monPdo;
    private static $monPdoGsb = null;

    /**
     * Constructeur public, crée l'instance de PDO qui sera sollicitée
     * pour toutes les méthodes de la classe
     */
    public function __construct($serveur, $bdd, $user, $mdp) {

        PdoGsb::$monPdo = new PDO("mysql:host=$serveur;dbname=$bdd", $user, $mdp);
        PdoGsb::$monPdo->query("SET CHARACTER SET utf8");
        PdoGsb::$monPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function _destruct() {
        PdoGsb::$monPdo = null;
    }

    /**
     * Fonction statique qui crée l'unique instance de la classe

     * Appel : $instancePdoGsb = PdoGsb::getPdoGsb();

     * @return l'unique objet de la classe PdoGsb
     */
    public static function getPdoGsb() {
        if (PdoGsb::$monPdoGsb == null) {
            PdoGsb::$monPdoGsb = new PdoGsb();
        }
        return PdoGsb::$monPdoGsb;
    }

    /**
     * Retourne les informations d'un visiteur

     * @param $login 
     * @param $mdp
     * @return l'id, le nom et le prénom sous la forme d'un tableau associatif 
     */
    public function getInfosVisiteur($login, $mdp) {

        $req = "select visiteur.id as id, visiteur.nom as nom, visiteur.prenom as prenom, typecompte.type, visiteur.typeCompte from visiteur
                INNER JOIN typecompte ON typecompte.id = visiteur.typeCompte
		        where visiteur.login = :log and visiteur.mdp=SHA1(:md)";
        //var_dump($req);
        $stmt = PdoGsb::$monPdo->prepare($req);
        $stmt->bindParam('log', $login);
        $stmt->bindParam('md', $mdp);
        $stmt->execute();
        $ligne = $stmt->fetch();
        //var_dump($ligne);
        return $ligne;
    }

    /**
     * Retourne sous forme d'un tableau associatif toutes les lignes de frais hors forfait
     * concernées par les deux arguments

     * La boucle foreach ne peut être utilisée ici car on procède
     * à une modification de la structure itérée - transformation du champ date-

     * @param $idVisiteur 
     * @param $mois sous la forme aaaamm
     * @return tous les champs des lignes de frais hors forfait sous la forme d'un tableau associatif 
     */
    public function getLesFraisHorsForfait($idVisiteur, $mois) {

        $req = "select
	lhf.id, lhf.idvisiteur,
    lhf.mois, lhf.libelle,
    lhf.date, lhf.montant,
    lhf.justificatif,
    lhf.etat,  e.libelle as libelleetat 
    
from 
	lignefraishorsforfait lhf, etat e 
    where
	lhf.etat = e.id
	and lhf.idvisiteur = :idVisiteur
	and lhf.mois = :mois";

        //$req = "select * from lignefraishorsforfait where lignefraishorsforfait.idvisiteur = :idVisiteur 
        //	and lignefraishorsforfait.mois = :mois ";
        $stmt = PdoGsb::$monPdo->prepare($req);
        $stmt->bindParam(':idVisiteur', $idVisiteur);
        $stmt->bindParam(':mois', $mois);
        $stmt->execute();
        $lesLignes = $stmt->fetchAll();
        $nbLignes = count($lesLignes);
        for ($i = 0; $i < $nbLignes; $i++) {
            $date = $lesLignes[$i]['date'];
            $lesLignes[$i]['date'] = dateAnglaisVersFrancais($date);
        }
        return $lesLignes;
    }

    public function getLesEtatFrais() {
        $req = "SELECT * FROM `etat` where id IN ('at','rf','rp','va')";
        $stmt = PdoGsb::$monPdo->prepare($req);
        $stmt->execute();
        $lesLignes = $stmt->fetchAll();
        return $lesLignes;
    }

    /**
     * Retourne le nombre de justificatif d'un visiteur pour un mois donné

     * @param $idVisiteur 
     * @param $mois sous la forme aaaamm
     * @return le nombre entier de justificatifs 
     */
    public function getNbjustificatifs($idVisiteur, $mois) {
        $req = "select fichefrais.nbjustificatifs as nb from  fichefrais where fichefrais.idvisiteur =:idVisiteur and fichefrais.mois = :mois";
        $stmt = PdoGsb::$monPdo->prepare($req);
        $stmt->bindParam(':idVisiteur', $idVisiteur);
        $stmt->bindParam(':mois', $mois);
        $stmt->execute();
        $laLigne = $stmt->fetch();
        return $laLigne['nb'];
    }

    /**
     * Retourne sous forme d'un tableau associatif toutes les lignes de frais au forfait
     * concernées par les deux arguments

     * @param $idVisiteur 
     * @param $mois sous la forme aaaamm
     * @return l'id, le libelle et la quantité sous la forme d'un tableau associatif 
     */
    public function getLesFraisForfait($idVisiteur, $mois) {
        $req = "select fraisforfait.id as idfrais, fraisforfait.libelle as libelle, 
		lignefraisforfait.quantite as quantite from lignefraisforfait inner join fraisforfait 
		on fraisforfait.id = lignefraisforfait.idfraisforfait
		where lignefraisforfait.idvisiteur = :idVisiteur and lignefraisforfait.mois= :mois 
		order by lignefraisforfait.idfraisforfait";
        $stmt = PdoGsb::$monPdo->prepare($req);
        $stmt->bindParam(':idVisiteur', $idVisiteur);
        $stmt->bindParam(':mois', $mois);
        $stmt->execute();
        $lesLignes = $stmt->fetchAll();
        return $lesLignes;
    }

    /**
     * Retourne tous les id de la table FraisForfait

     * @return un tableau associatif 
     */
    public function getLesIdFrais() {
        $req = "select fraisforfait.id as idfrais from fraisforfait order by fraisforfait.id";
        $stmt = PdoGsb::$monPdo->prepare($req);
        $stmt->execute();
        $lesLignes = $stmt->fetchAll();
        return $lesLignes;
    }

    /**
     * Met à jour la table ligneFraisForfait

     * Met à jour la table ligneFraisForfait pour un visiteur et
     * un mois donné en enregistrant les nouveaux montants

     * @param $idVisiteur 
     * @param $mois sous la forme aaaamm
     * @param $lesFrais tableau associatif de clé idFrais et de valeur la quantité pour ce frais
     * @return un tableau associatif 
     */
    public function majFraisForfait($idVisiteur, $mois, $lesFrais) {
        $lesCles = array_keys($lesFrais);
        foreach ($lesCles as $unIdFrais) {
            $qte = $lesFrais[$unIdFrais];
            $req = "update lignefraisforfait set lignefraisforfait.quantite = $qte
			where lignefraisforfait.idvisiteur = :idVisiteur and lignefraisforfait.mois = :mois
			and lignefraisforfait.idfraisforfait = :unIdFrais";
            $stmt = PdoGsb::$monPdo->prepare($req);
            $stmt->bindParam(':idVisiteur', $idVisiteur);
            $stmt->bindParam(':mois', $mois);
            $stmt->bindParam(':unIdFrais', $unIdFrais);
            $stmt->execute();
        }
    }

    /**
     * met à jour le nombre de justificatifs de la table ficheFrais
     * pour le mois et le visiteur concerné

     * @param $idVisiteur 
     * @param $mois sous la forme aaaamm
     */
    public function majNbJustificatifs($idVisiteur, $mois, $nbJustificatifs) {
        $req = "update fichefrais set nbjustificatifs = :nbJustificatifs 
                where fichefrais.idvisiteur = :idVisiteur and fichefrais.mois = :mois";
        $stmt = PdoGsb::$monPdo->prepare($req);
        $stmt->bindParam(':idVisiteur', $idVisiteur);
        $stmt->bindParam(':mois', $mois);
        $stmt->bindParam(':nbJustificatifs', $nbJustificatifs);
        $stmt->execute();
    }

    /**
     * Teste si un visiteur possède une fiche de frais pour le mois passé en argument

     * @param $idVisiteur 
     * @param $mois sous la forme aaaamm
     * @return vrai ou faux 
     */
    public function estPremierFraisMois($idVisiteur, $mois) {
        $ok = false;
        $req = "select count(*) as nblignesfrais from fichefrais 
                where fichefrais.mois = :mois and fichefrais.idvisiteur = :idVisiteur";
        $stmt = PdoGsb::$monPdo->prepare($req);
        $stmt->bindParam(':idVisiteur', $idVisiteur);
        $stmt->bindParam(':mois', $mois);
        $stmt->execute();
        $laLigne = $stmt->fetch();
        if ($laLigne['nblignesfrais'] == 0) {
            $ok = true;
        }
        return $ok;
    }

    /**
     * Retourne le dernier mois en cours d'un visiteur

     * @param $idVisiteur 
     * @return le mois sous la forme aaaamm
     */
    public function dernierMoisSaisi($idVisiteur) {
        $req = "select max(mois) as derniermois from fichefrais where fichefrais.idvisiteur = :idVisiteur";
        $stmt = PdoGsb::$monPdo->prepare($req);
        $stmt->bindParam(':idVisiteur', $idVisiteur);
        $stmt->execute();
        $laLigne = $stmt->fetch();
        $dernierMois = $laLigne['derniermois'];
        return $dernierMois;
    }

    /**
     * Crée une nouvelle fiche de frais et les lignes de frais au forfait pour un visiteur et un mois donnés

     * récupère le dernier mois en cours de traitement, met à 'CL' son champs idEtat, crée une nouvelle fiche de frais
     * avec un idEtat à 'CR' et crée les lignes de frais forfait de quantités nulles 
     * @param $idVisiteur 
     * @param $mois sous la forme aaaamm
     */
    public function creeNouvellesLignesFrais($idVisiteur, $mois) {
        $dernierMois = $this->dernierMoisSaisi($idVisiteur);
        $laDerniereFiche = $this->getLesInfosFicheFrais($idVisiteur, $dernierMois);
        if ($laDerniereFiche['idetat'] == 'cr') {
            $this->majEtatFicheFrais($idVisiteur, $dernierMois, 'cl');
        }
        $req = "insert into fichefrais(idvisiteur,mois,nbjustificatifs,montantvalide,datemodif,idetat) 
                values(:idVisiteur,:mois,0,0,now(),'cr')";
        $stmt = PdoGsb::$monPdo->prepare($req);
        $stmt->bindParam(':idVisiteur', $idVisiteur);
        $stmt->bindParam(':mois', $mois);
        $stmt->execute();
        $lesIdFrais = $this->getLesIdFrais();
        foreach ($lesIdFrais as $uneLigneIdFrais) {
            $unIdFrais = $uneLigneIdFrais['idfrais'];
            $req = "insert into lignefraisforfait(idvisiteur,mois,idFraisForfait,quantite) 
                        values(:idVisiteur,:mois,:unIdFrais,0)";
            $stmt = PdoGsb::$monPdo->prepare($req);
            $stmt->bindParam(':idVisiteur', $idVisiteur);
            $stmt->bindParam(':mois', $mois);
            $stmt->bindParam(':unIdFrais', $unIdFrais);
            $stmt->execute();
        }
    }

    /**
     * Crée un nouveau frais hors forfait pour un visiteur un mois donné
     * à partir des informations fournies en paramètre

     * @param $idVisiteur 
     * @param $mois sous la forme aaaamm
     * @param $libelle : le libelle du frais
     * @param $date : la date du frais au format français jj//mm/aaaa
     * @param $montant : le montant
     */
    public function creeNouveauFraisHorsForfait($idVisiteur, $mois, $libelle, $date, $montant) {
        $dateFr = dateFrancaisVersAnglais($date);
        $req = "insert into lignefraishorsforfait (idvisiteur,mois,libelle,date,montant,etat)
                values(:idVisiteur,:mois,:libelle,'$dateFr',:montant,'at')";
        $stmt = PdoGsb::$monPdo->prepare($req);
        $stmt->bindParam(':idVisiteur', $idVisiteur);
        $stmt->bindParam(':mois', $mois);
        $stmt->bindParam(':libelle', $libelle);
        $stmt->bindParam(':montant', $montant);
        $stmt->execute();
    }

    /**
     * Supprime le frais hors forfait dont l'id est passé en argument

     * @param $idFrais 
     */
    public function supprimerFraisHorsForfait($idFrais) {
        $req = "delete from lignefraishorsforfait where lignefraishorsforfait.id =:idFrais ";
        $stmt = PdoGsb::$monPdo->prepare($req);
        $stmt->bindParam(':idFrais', $idFrais);
        $stmt->execute();
    }

    /**
     * Retourne les mois pour lesquel un visiteur a une fiche de frais

     * @param $idVisiteur 
     * @return un tableau associatif de clé un mois -aaaamm- et de valeurs l'année et le mois correspondant 
     */
    public function getLesMoisDisponibles($idVisiteur) {
        $req = "select fichefrais.mois as mois from  fichefrais where fichefrais.idvisiteur =:idVisiteur 
                order by fichefrais.mois desc ";
        $stmt = PdoGsb::$monPdo->prepare($req);
        $stmt->bindParam(':idVisiteur', $idVisiteur);
        $stmt->execute();
        $laLigne = $stmt->fetch();
        $lesMois = array();
        while ($laLigne != null) {
            $mois = $laLigne['mois'];
            $numAnnee = substr($mois, 0, 4);
            $numMois = substr($mois, 4, 2);
            $lesMois["$mois"] = array(
                "mois" => "$mois",
                "numAnnee" => "$numAnnee",
                "numMois" => "$numMois"
            );
            $laLigne = $stmt->fetch();
        }
        return $lesMois;
    }

    /**
     * Retourne les informations d'une fiche de frais d'un visiteur pour un mois donné

     * @param $idVisiteur 
     * @param $mois sous la forme aaaamm
     * @return un tableau avec des champs de jointure entre une fiche de frais et la ligne d'état 
     */
    public function getLesInfosFicheFrais($idVisiteur, $mois) {
        $req = "select fichefrais.idetat as idetat, fichefrais.datemodif as datemodif, fichefrais.nbjustificatifs as nbjustificatifs, 
                        fichefrais.montantvalide as montantvalide, etat.libelle as libetat from  fichefrais inner join etat on fichefrais.idetat = etat.id 
                        where fichefrais.idvisiteur = :idvisiteur and fichefrais.mois = :mois";
        $stmt = PdoGsb::$monPdo->prepare($req);
        $stmt->bindParam(':idvisiteur', $idVisiteur);
        $stmt->bindParam(':mois', $mois);
        $stmt->execute();
        $laLigne = $stmt->fetch();
        return $laLigne;
    }

    /**
     * Modifie l'état et la date de modification d'une fiche de frais

     * Modifie le champ idEtat et met la date de modif à aujourd'hui
     * @param $idVisiteur 
     * @param $mois sous la forme aaaamm
     */
    public function majEtatFicheFrais($idVisiteur, $mois, $etat) {
        $req = "update fichefrais set idetat = :etat, datemodif = now() 
                where fichefrais.idvisiteur = :idVisiteur and fichefrais.mois = :mois";
        $stmt = PdoGsb::$monPdo->prepare($req);
        $stmt->bindParam(':idVisiteur', $idVisiteur);
        $stmt->bindParam(':mois', $mois);
        $stmt->bindParam(':etat', $etat);
        $stmt->execute();
    }

    /**
     * Retourne toutes les fiches de frais dont l'état est à validé
     * @return type
     */
    public function getLesFichesValidees() {
        $req = "select * from fichefrais
                    inner join etat on fichefrais.idetat = etat.id
                    inner join visiteur on visiteur.id = fichefrais.idVisiteur
                    where idetat = 'VA'";
        $stmt = PdoGsb::$monPdo->prepare($req);
        $stmt->execute();
        $lesLignes = $stmt->fetchAll();
        return $lesLignes;
    }

    /**
     * Vérifie si un frais existe pour un visiteur et un mois donné
     * @param type $idVisiteur
     * @param type $mois
     * @param type $idFrais
     * @return 1 ou 0
     */
    public function estValideSuppressionFrais($idVisiteur, $mois, $idFrais) {
        $req = "select count(*) as nb from lignefraishorsforfait 
            where lignefraishorsforfait.id=:idfrais and lignefraishorsforfait.mois=:mois
            and lignefraishorsforfait.idvisiteur=:idvisiteur";
        $stmt = PdoGsb::$monPdo->prepare($req);
        $stmt->bindParam(':idfrais', $idFrais);
        $stmt->bindParam(':mois', $mois);
        $stmt->bindParam(':idvisiteur', $idVisiteur);
        $stmt->execute();
        $ligne = $stmt->fetch();
        return $ligne['nb'];
    }

    // FONCTION AJOUTE

    /**
     * fonction qui rassemble les parametres du tableau pdf 
     * @param type $idvisiteur
     * @param type $mois
     * @return type
     */
    public function getLesFraisForfaitPdf($idvisiteur, $mois) {
        $req = "select fraisforfait.id as idfrais, fraisforfait.libelle as libelle,
		lignefraisforfait.quantite as quantite, fraisforfait.montant as montant, (quantite * montant) as total from lignefraisforfait inner join fraisforfait
		on fraisforfait.id = lignefraisforfait.idfraisforfait
		where lignefraisforfait.idvisiteur ='$idvisiteur' and lignefraisforfait.mois='$mois'
		order by lignefraisforfait.idfraisforfait";
        $res = PdoGsb::$monPdo->query($req);
        $lesLignes = $res->fetchAll();
        return $lesLignes;
    }

    /**
     * retourne le total des montants des frais forfaits en fonction du visiteur
     * @param type $idvisiteur
     * @param type $mois
     * @return type
     */
    public function getTotalForfaitPdf($idvisiteur, $mois) {
        $req = "select sum(total) as total
                from(select sum(quantite * montant) as total
                from lignefraisforfait inner join fraisforfait
		on fraisforfait.id = lignefraisforfait.idfraisforfait
		where lignefraisforfait.idvisiteur ='$idvisiteur' and lignefraisforfait.mois='$mois'
                union all
                select sum(montant) as total from lignefraishorsforfait where lignefraishorsforfait.idvisiteur ='$idvisiteur'
		and lignefraishorsforfait.mois = '$mois') T";
        $res = PdoGsb::$monPdo->query($req);
        $lesLignes = $res->fetchAll();
        return $lesLignes;
    }

    /**
     * Retourne sous forme d'un tableau associatif toutes les lignes de TYPEVEHICULE
     * @return l'id, le typevehicule et la puissance sous la forme d'un tableau associatif
     */
    public function getLesPuissances() {
        $req = "SELECT * FROM `typevehicule`";
        $res = PdoGsb::$monPdo->query($req);
        $lesLignes = $res->fetchAll();
        return $lesLignes;
    }

    /**
     * met à jour le forfait kilometrique
     * pour le mois et le visiteur concerné
     * @param $idvisiteur $mois $choixPuissance
     * @param $mois sous la forme aaaamm
     */
    public function getForfaitKilometrique($idvisiteur) {
        $req = "select DISTINCT tv.typevehicule, tv.puissance, tv.montant from typevehicule tv, vehiculevisiteur vv, lignefraisforfait lff where vv.idvehicule = tv.id and lff.idvisiteur = vv.idvisiteur and vv.idvisiteur = '$idvisiteur' ";
        $res = PdoGsb::$monPdo->query($req);
        $lapuissance = $res->fetch();
        return $lapuissance;
    }

    /**
     * 
     * @param type $idvisteur
     * @param type $choixMois
     */
    public function getMontantForfaitKilometrique($idvisteur, $choixMois) {
        $req = "select SUM(tv.montant * lff.quantite) as indeminiteKilometrique from lignefraisforfait lff, typevehicule tv, vehiculevisiteur vv where
                lff.idfraisforfait = vv.idKm
                and tv.id = vv.idvehicule
                and lff.idvisiteur = vv.idvisiteur
                and lff.idvisiteur ='$idVisiteur' and mois ='$choixMois'";
        $res = PdoGsb::$monPdo->query($req);
        $montantIndeminiteKilometrique = $res->fetch();
        return $montantIndeminiteKilometrique;
    }

    /**
     * Retourne les mois pour lesquel des fiches de frais sont à valider 
     * @param Aucun
     * @return un tableau associatif de clé un mois -aaaamm- et de valeurs l'année et le mois correspondant
     */
    public function getLesMoisAvalider() {
        $req = "SELECT mois from fichefrais where idetat ='cl' group by mois ORDER BY `fichefrais`.`mois`  DESC";
        $res = PdoGsb::$monPdo->query($req);
        $lesMois = array();
        $laLigne = $res->fetch();
        while ($laLigne != null) {
            $mois = $laLigne['mois'];
            $numAnnee = substr($mois, 0, 4);
            $numMois = substr($mois, 4, 2);
            $lesMois["$mois"] = array(
                "mois" => "$mois",
                "numAnnee" => "$numAnnee",
                "numMois" => "$numMois"
            );
            $laLigne = $res->fetch();
        }
        return $lesMois;
    }

    /**
     * Retourne les mois pour lesquel des fiches de frais sont à valider 
     * @return type
     */
    public function getLesMoisAPayer() {
        $req = "SELECT mois from fichefrais where idetat ='va' group by mois ORDER BY `fichefrais`.`mois`  DESC";
        $res = PdoGsb::$monPdo->query($req);
        $lesMois = array();
        $laLigne = $res->fetch();
        while ($laLigne != null) {
            $mois = $laLigne['mois'];
            $numAnnee = substr($mois, 0, 4);
            $numMois = substr($mois, 4, 2);
            $lesMois["$mois"] = array(
                "mois" => "$mois",
                "numAnnee" => "$numAnnee",
                "numMois" => "$numMois"
            );
            $laLigne = $res->fetch();
        }
        return $lesMois;
    }

    /**
     * fonction qui renvoie l'ensemble des visiteurs de la table & qui ne sont pas comptable
     * @return un tableau de visiteur
     */
    public function getLesVisiteurs() {
        $req = "select id, nom as nom, prenom as prenom from visiteur where typecompte=1 order by nom asc";
        $res = PdoGsb::$monPdo->query($req);
        $lesVisiteurs = array();
        $laLigne = $res->fetch();
        while ($laLigne != null) {
            $id = $laLigne['id'];
            $nom = $laLigne['nom'];
            $prenom = $laLigne['prenom'];
            $lesVisiteurs["$id"] = array(
                "id" => "$id",
                "nom" => "$nom",
                "prenom" => "$prenom"
            );
            $laLigne = $res->fetch();
        }
        return $lesVisiteurs;
    }

    /**
     * renvoie les visiteurs qui ont une fiche de frais pour le mois en paramètre
     * @param $choixMois
     * @return un tableau contenant les visiteurs
     */
    public function getLesVisiteursAValider($choixMois) {
        $req = "SELECT id,nom as nom, prenom as prenom from fichefrais join visiteur v where idVisiteur = v.id and mois ='$choixMois' and typecompte = 1 and idetat = 'cl'";
        $res = PdoGsb::$monPdo->query($req);
        $lesVisiteursValidation = array();
        $laLigne = $res->fetch();
        while ($laLigne != null) {
            $id = $laLigne['id'];
            $nom = $laLigne['nom'];
            $prenom = $laLigne['prenom'];
            $lesVisiteursValidation["$id"] = array(
                "id" => "$id",
                "nom" => "$nom",
                "prenom" => "$prenom"
            );
            $laLigne = $res->fetch();
        }
        return $lesVisiteursValidation;
    }

    public function getLesVisiteursAPayer($choixMois) {
        $req = "SELECT id,nom as nom, prenom as prenom from fichefrais join visiteur  where idVisiteur = id and mois = '$choixMois' and typecompte = 1 and idetat = 'va'";
        $res = PdoGsb::$monPdo->query($req);
        $lesVisiteursValidation = array();
        $laLigne = $res->fetch();
        while ($laLigne != null) {
            $id = $laLigne['id'];
            $nom = $laLigne['nom'];
            $prenom = $laLigne['prenom'];
            $lesVisiteursValidation["$id"] = array(
                "id" => "$id",
                "nom" => "$nom",
                "prenom" => "$prenom"
            );
            $laLigne = $res->fetch();
        }
        return $lesVisiteursValidation;
    }

    public function getLeVisiteur($idVisiteur) {
        $req = "select * from visiteur where id ='$idVisiteur'";
        $resultat = PdoGsb::$monPdo->query($req);
        $fetch = $resultat->fetch();
        return $fetch;
    }

    /**
     * fonction qui change l'etat d'un frais (ce frais n'est pas supprimer mais il change d'état)
     * @param $id du frais hors forfait
     */
    public function setEtatFraisHorsForfait($id, $etat) {
        $req = "update lignefraishorsforfait set etat = '$etat' where id = '$id'";
        //echo $req;
        PdoGsb::$monPdo->exec($req);
    }

    /**
     * fonction qui reporte un frais (ce frais est envoyé au mois suivant)
     * @param $id du frais hors forfait
     */
    public function ReportFraisHorsForfait($moisSuivant, $idVisiteur, $id) {
        $req = "UPDATE lignefraishorsforfait SET mois ='$moisSuivant', etat = 'rp' WHERE idvisiteur='$idVisiteur' and id ='$id'";
        PdoGsb::$monPdo->exec($req);
    }

    /**
     * fonction qui valide un frais (ce frais change d'état)
     * @param $id du frais hors forfait
      public function validerFraisHorsForfait($id) {
      $req = "update lignefraishorsforfait set etat ='va' where id = '$id'";
      //echo $req;
      PdoGsb::$monPdo->exec($req);
      }
     */
    /*
     * Valide la fiche de frais et met a jour le montant de la fiche de frais
     * @param $idVisiteur, $choixMois de la fiche de frais
     */

    public function validerFicheFrais($idVisiteur, $choixMois, $montantTotal) {
        $req = "update fichefrais set idetat = 'va', montantvalide = '$montantTotal', datemodif= now() where idvisiteur = '$idVisiteur' and mois ='$choixMois' ";
        //echo $req;
        PdoGsb::$monPdo->exec($req);
    }

    /*
     * Met en paiement la fiche de frais du visiteur choisi pour le mois donner
     */

    public function mettreEnPaiement($idVisiteur, $choixMois) {
        $req = "update fichefrais set idetat = 'mp',datemodif= now() where idvisiteur = '$idVisiteur' and mois = '$choixMois'";
        //echo $req;
        PdoGsb::$monPdo->exec($req);
    }

    /**
     * fonction Verifie si il existe des Frais hors forfait pour le fiche du Mois en paramètre et renvoi faut si des frais sont en attente ou reporter
     * @param $idVisiteur, $mois
     */
    public function verifEtatFraisHF($idVisiteur, $choixMois) {

        $ok = false;
        $req = "select count(*) as nblignesfraisHF from lignefraishorsforfait where idvisiteur ='$idVisiteur' and etat NOT IN('va','rf') and mois='$choixMois' ";
        $res = PdoGsb::$monPdo->query($req);
        $laLigne = $res->fetch();
        if ($laLigne['nblignesfraisHF'] == 0) {
            $ok = true;
        }
        return $ok;
    }

    /*
     * Calcul le montant Total des frais du visiteur 
     */

    public function getMontantTotal($idVisiteur, $choixMois) {
        $req = "select sum(montant) as montantTotalFraisHF from lignefraishorsforfait where idvisiteur='$idVisiteur' and mois='$choixMois' and etat='va'";
        $res = PdoGsb::$monPdo->query($req);
        $montantHF = $res->fetch();


        $req = "select SUM(tv.montant * lff.quantite) as indeminiteKilometrique from lignefraisforfait lff, typevehicule tv, vehiculevisiteur vv where
                lff.idfraisforfait = vv.idKm
                and tv.id = vv.idvehicule
                and lff.idvisiteur = vv.idvisiteur
                and lff.idvisiteur ='$idVisiteur' and mois ='$choixMois'";
        $res = PdoGsb::$monPdo->query($req);
        $montantIndeminiteKilometrique = $res->fetch();

        $req = "select SUM(montant * quantite) as montantFraisForfait from fraisforfait inner join lignefraisforfait on fraisforfait.id = lignefraisforfait.idfraisforfait where idvisiteur = '$idVisiteur' and mois ='$choixMois'";
        $res = PdoGsb::$monPdo->query($req);
        $montantForfait = $res->fetch();

        $montantTotal = $montantHF['montantTotalFraisHF'] + $montantForfait['montantFraisForfait'] + $montantIndeminiteKilometrique['indeminiteKilometrique'];
        return $montantTotal;
    }

    /**
     * fonction Calcul le montant total des frais hors forfait validé
     * @param $idVisiteur, $mois
     */
    public function getTotalHFValide($idVisiteur, $choixMois) {
        $req = "select sum(montant) as montantTotalFraisHF from lignefraishorsforfait where idvisiteur='$idVisiteur' and mois='$choixMois' and etat='va'";
        $res = PdoGsb::$monPdo->query($req);
        $montantHF = $res->fetch();
        return $montantHF;
    }

    /**
     * fonction Calcul le montant total des frais hors forfait refusé
     * @param $idVisiteur, $mois
     */
    public function getTotalHFRefuse($idVisiteur, $choixMois) {
        $req = "select sum(montant) as montantTotalFraisRF from lignefraishorsforfait where idvisiteur='$idVisiteur' and mois='$choixMois' and etat='rf'";
        $res = PdoGsb::$monPdo->query($req);
        $montantRF = $res->fetch();
        return $montantRF;
    }

    /**
     * fonction Calcul le montant total des frais forfaitisés
     * @param $idVisiteur, $mois
     */
    public function getTotalForfait($idVisiteur, $choixMois) {
        $req1 = "select SUM(tv.montant * lff.quantite) as indeminiteKilometrique from lignefraisforfait lff, typevehicule tv, vehiculevisiteur vv where
                lff.idfraisforfait = vv.idKm
                and tv.id = vv.idvehicule
                and lff.idvisiteur = vv.idvisiteur
                and lff.idvisiteur ='$idVisiteur' and mois ='$choixMois'";
        $res1 = PdoGsb::$monPdo->query($req1);
        $montantIndeminiteKilometrique = $res1->fetch();

        $req = "select SUM(montant * quantite) as montantFraisForfait from fraisforfait inner join lignefraisforfait on fraisforfait.id = lignefraisforfait.idfraisforfait where idvisiteur = '$idVisiteur' and mois ='$choixMois'";
        $res = PdoGsb::$monPdo->query($req);
        $montantForfait = $res->fetch();

        $montantTotal = $montantForfait['montantFraisForfait'] + $montantIndeminiteKilometrique['indeminiteKilometrique'];
        return $montantTotal;
    }

}

?>