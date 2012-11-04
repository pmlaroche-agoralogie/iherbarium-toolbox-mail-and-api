<?php // by Dany
namespace iHerbarium;

function adaptHeures($param){
    
    $now = date('Y-m-d H:i:s');
    $datetime1 = new \DateTime($param);
    $datetime2 = new \DateTime($now);
    $interval = $datetime1->diff($datetime2);
    
    //Dfinitions des variables (info google)
    $nbrsecAnnee = 31556926;
    $nbrsecMois = 2629743.83;
    $nbrsecJour = 86400;
    $nbrsecHeure = 3600;
    $nbrsecMinute = 60;
    $ilya = "il y a ";
    $pluriel = 's';
  
    //Calcul de la dure en seconde 
    $duree = ($interval->format('%Y')) * $nbrsecAnnee;
    $duree += ($interval->format('%m')) * $nbrsecMois;
    $duree += ($interval->format('%d')) * $nbrsecJour;
    $duree += ($interval->format('%H')) * $nbrsecHeure;
    $duree += ($interval->format('%i')) * $nbrsecMinute;
    $duree += ($interval->format('%s'));
    
    //Traitement 
    switch ($duree){
        case ($duree < $nbrsecMinute) : {
           if ($duree==1) $pluriel='';
           $result = $ilya. $duree." seconde" .$pluriel;
        } break;
        
        case ($duree < $nbrsecHeure) : {
           $duree = intval(abs($duree/$nbrsecMinute));
           if ($duree==1) $pluriel='';
           $result = $ilya. $duree. ' minute'.$pluriel;
        }; break;
        
        case ($duree < $nbrsecJour) :{
           $duree = intval(abs($duree/$nbrsecHeure));
           if ($duree==1) $pluriel='';
           $result = $ilya. $duree.  ' heure'.$pluriel ;
        }break;
        
        case ($duree < ($nbrsecMois+($nbrsecJour*10))) : {
            $duree = intval(abs($duree/$nbrsecJour));
            if ($duree==1) $pluriel='';
            $result = $ilya. $duree. ' jour'.$pluriel ;
        }break;
        
        case ($duree < ($nbrsecAnnee+($nbrsecMois*3))) : {
            $duree = intval(abs($duree/$nbrsecMois));
            if ($duree==1) $pluriel='';
            $result = $ilya. $duree. ' mois' ;
        }break;
        
        default: {
            $duree =intval(abs($duree/$nbrsecAnnee));
            if ($duree==1) $pluriel='';
            $result = $ilya. $duree. ' an' .$pluriel ;
        }
        
    }
    
    return ($result);
}
?>
