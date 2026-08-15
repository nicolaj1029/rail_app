<?php
/**
 * Temporary tc6 wrapper for legacy remedies.
 *
 * Keep the real legacy content intact while we move the post-incident flow
 * over one step at a time. This avoids the simplified placeholder UI and
 * lets the user inspect the actual Art. 18 form inside the tc6 shell.
 */

$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$railPostIncident = is_array($railPostIncident ?? null) ? (array)$railPostIncident : [];
$transportMode = strtolower((string)($form['transport_mode'] ?? ($meta['transport_mode'] ?? 'rail')));
$travelState = strtolower((string)($flags['travel_state'] ?? ($form['travel_state'] ?? 'completed')));
$railEnabledRemedies = (array)($railPostIncident['enabled_remedies'] ?? []);
$uiLanguage = strtolower((string)($uiLanguage ?? 'da'));
$remediesTranslations = [
    'fr' => [
        'Tilbage' => 'Retour',
        'Naeste trin' => 'Etape suivante',
        'Billet, grunddata' => 'Billet, donnees de base',
        'Vaelg afgang' => 'Choix du depart',
        'Vaelg fly' => 'Choix du vol',
        'Haendelse' => 'Incident',
        'Valg efter haendelsen' => 'Choix apres l incident',
        'Refusion, ombooking' => 'Remboursement, reacheminement',
        'Refund, ombooking' => 'Remboursement, reacheminement',
        'Assistance' => 'Assistance',
        'Kontakt, opret sag' => 'Contact, creation du dossier',
        'Nedgradering' => 'Declassement',
        'Start & Rejsestatus' => 'Depart et statut du voyage',
        'Billet / Ticketless + Grunddata' => 'Billet / sans billet + donnees de base',
        'Haendelseskede + gating' => 'Chaine d incident + filtrage',
        'Refusion / Omlaegning (Art. 18)' => 'Remboursement / reacheminement (art. 18)',
        'Beregning & Resultat (Art. 19)' => 'Calcul et resultat (art. 19)',
        'Ansoeger & Udbetaling' => 'Demandeur et paiement',
        'Samtykke & Ekstra info' => 'Consentement et informations complementaires',
        'Flow' => 'Parcours',
        'Igangvaerende rejse' => 'Voyage en cours',
        'Foer afgang' => 'Avant le depart',
        'Afsluttet rejse' => 'Voyage termine',
        'Tilbagebetaling' => 'Remboursement',
        'Ombooking hurtigst muligt' => 'Reacheminement au plus vite',
        'Ombooking senere' => 'Reacheminement plus tard',
        'Fortsat rejse' => 'Voyage poursuivi',
        'Intet reelt valg' => 'Aucun vrai choix',
        'Aktiv' => 'Actif',
        'Afventer' => 'En attente',
        'Refusion / ombooking' => 'Remboursement / reacheminement',
        'Operatoer' => 'Operateur',
        'Ikke valgt endnu' => 'Pas encore selectionne',
        'Trin ' => 'Etape ',
        'Afklar om du har krav paa refund, ombooking eller videre assistance efter haendelsen.' => 'Indiquez si vous avez droit a un remboursement, un reacheminement ou une assistance complementaire apres l incident.',
        'TRIN 6 - Tilbagebetaling eller ombooking (faerge)' => 'ETAPE 6 - Remboursement ou reacheminement (ferry)',
        'TRIN 6 - Tilbagebetaling eller ombooking (bus)' => 'ETAPE 6 - Remboursement ou reacheminement (bus)',
        'TRIN 6 - Refund eller ombooking (fly)' => 'ETAPE 6 - Remboursement ou reacheminement (avion)',
        'TRIN 6 - Refusion eller omlaegning' => 'ETAPE 6 - Remboursement ou reacheminement',
        'TRIN 6 - Dine valg' => 'ETAPE 6 - Vos choix',
        'Vaelg det spor, der passer bedst til, hvordan rejsen skulle fortsaette.' => 'Choisissez le parcours qui correspond le mieux a la facon dont le voyage devait se poursuivre.',
        'Status: Rejsen er afsluttet. Besvar ud fra hvad der faktisk skete.' => 'Statut : le voyage est termine. Repondez selon ce qui s est reellement passe.',
        'Status: Rejsen er i gang. Trin 4 er nu vurderet, og her vaelger du hvad du vil goere nu.' => 'Statut : le voyage est en cours. L etape 4 a maintenant ete evaluee, et vous choisissez ici ce que vous voulez faire maintenant.',
        'Status: Rejsen er endnu ikke paabegyndt. Besvar ud fra, hvad du forventer at goere ved forsinkelse/aflysning.' => 'Statut : le voyage n a pas encore commence. Repondez selon ce que vous pensez faire en cas de retard ou d annulation.',
        'Faergerejsen er i gang - hvad er dit valg nu?' => 'La traversee en ferry est en cours - quel est votre choix maintenant ?',
        'Faergerejsen starter senere - hvad vil du vaelge, hvis det sker?' => 'La traversee en ferry commence plus tard - que choisiriez-vous si cela se produisait ?',
        'Faergerejsen er afsluttet - hvad skete der?' => 'La traversee en ferry est terminee - que s est-il passe ?',
        'Busturen er i gang - hvad er dit valg nu?' => 'Le trajet en bus est en cours - quel est votre choix maintenant ?',
        'Busturen starter senere - hvad vil du vaelge, hvis det sker?' => 'Le trajet en bus commence plus tard - que choisiriez-vous si cela se produisait ?',
        'Busturen er afsluttet - hvad skete der?' => 'Le trajet en bus est termine - que s est-il passe ?',
        'Flighten er i gang - hvad er dit valg nu?' => 'Le vol est en cours - quel est votre choix maintenant ?',
        'Flighten starter senere - hvad vil du vaelge, hvis det sker?' => 'Le vol commence plus tard - que choisiriez-vous si cela se produisait ?',
        'Flighten er afsluttet - hvad skete der?' => 'Le vol est termine - que s est-il passe ?',
        'Togrejsen er i gang - hvad er dit valg nu?' => 'Le voyage en train est en cours - quel est votre choix maintenant ?',
        'Togrejsen starter senere - hvad vil du vaelge, hvis det sker?' => 'Le voyage en train commence plus tard - que choisiriez-vous si cela se produisait ?',
        'Togrejsen er afsluttet - hvad skete der?' => 'Le voyage en train est termine - que s est-il passe ?',
        'Rute-/forbindelsesforslag (Google Maps, valgfrit)' => 'Suggestions d itineraire / de correspondance (Google Maps, optionnel)',
        'Ruter mellem lufthavne/transportpunkter (Google Maps, valgfrit)' => 'Itineraires entre aeroports / points de transport (Google Maps, optionnel)',
        'Ruter (Google Maps, valgfrit)' => 'Itineraires (Google Maps, optionnel)',
        'Hent forslag' => 'Recuperer des suggestions',
        'Aabn i Google Maps' => 'Ouvrir dans Google Maps',
        'Aabn i Google Maps (detaljer)' => 'Ouvrir dans Google Maps (details)',
        'Angiv start og destination foerst.' => 'Indiquez d abord le depart et la destination.',
        'Henter...' => 'Chargement...',
        'Fejl' => 'Erreur',
        'Operateuren lod mig bruge min billet paa en anden afgang' => 'L operateur m a laisse utiliser mon billet sur un autre depart',
        'Operateuren omlagde mig via anden rute/andet tog' => 'L operateur m a reroute via un autre itineraire / un autre train',
        'Operateuren tilbod bus/taxi/anden transport' => 'L operateur a propose un bus, un taxi ou un autre transport',
        'Operateuren aendrede/ombookede min billet' => 'L operateur a modifie / rebooke mon billet',
        'Hvordan vil du fortsaette rejsen?' => 'Comment souhaitez-vous poursuivre le voyage ?',
        'Jeg oensker refusion' => 'Je souhaite un remboursement',
        'Jeg oensker omlaegning hurtigst muligt' => 'Je souhaite un reacheminement au plus vite',
        'Jeg oensker omlaegning senere (efter eget valg)' => 'Je souhaite un reacheminement plus tard (a mon choix)',
        'Returtransport' => 'Transport de retour',
        'Brug Google Maps i denne sag' => 'Utiliser Google Maps dans ce dossier',
        'Fra (station)' => 'Depuis (gare)',
        'Til (destination)' => 'Vers (destination)',
        'Hvilken station er du paa?' => 'Dans quelle gare etes-vous ?',
        'Hvilken station skal du tilbage til?' => 'Vers quelle gare devez-vous retourner ?',
        'Hvilken station omlagde du til?' => 'Vers quelle gare avez-vous ete reachemine ?',
        'Anden station' => 'Autre gare',
        'Naermeste station' => 'Gare la plus proche',
        'Ved ikke' => 'Je ne sais pas',
        'Vaelg' => 'Choisir',
        'Ja' => 'Oui',
        'Nej' => 'Non',
        'Tog' => 'Train',
        'Andet' => 'Autre',
        'Refusionsnaer udgiftstype' => 'Type de frais proche du remboursement',
        'Udgiftstype' => 'Type de frais',
        'Transport de retour / tilbage til afgangssted' => 'Transport de retour / retour au lieu de depart',
        'Samkoersel / rideshare' => 'Covoiturage / rideshare',
        'Andet tog / ny togbillet' => 'Autre train / nouveau billet de train',
        'Vaelg den naermeste kategori. Beloeb, valuta og kvitteringer registreres senere i backend.' => 'Choisissez la categorie la plus proche. Le montant, la devise et les justificatifs seront ajoutes plus tard dans le back-office.',
        'Omlaegning' => 'Reacheminement',
        'Hvorledes blev/skal rejsen fortsaette?' => 'Comment le voyage a-t-il continue ou doit-il continuer ?',
        'Viderefoerelse registreret' => 'Poursuite du voyage enregistree',
        'Dette spor aabner ikke rail-merudgifter i Art. 18-remedies-trinnet.' => 'Ce parcours n ouvre pas de frais rail supplementaires dans l etape des recours art. 18.',
        'Var selvkobet godkendt af operatoeren?' => 'L achat effectue par vous-meme etait-il approuve par l operateur ?',
        'Fik du besked om mulighederne for omlaegning inden for 100 minutter? (Art. 18(3))' => 'Avez-vous recu des informations sur les possibilites de reacheminement dans les 100 minutes ? (art. 18(3))',
        'Vi bruger planlagt afgang + forste omlaegnings-besked til at vurdere 100-min-reglen.' => 'Nous utilisons le depart prevu et le premier message de reacheminement pour evaluer la regle des 100 minutes.',
        'Kunne den tilbudte omlaegning bruges?' => 'Le reacheminement propose pouvait-il etre utilise ?',
        'Hvorfor kunne den tilbudte omlaegning ikke bruges?' => 'Pourquoi le reacheminement propose ne pouvait-il pas etre utilise ?',
        'Egne alternative udgifter daekkes normalt ikke' => 'Les frais alternatifs personnels ne sont normalement pas couverts',
        'Egne alternative udgifter daekkes normalt ikke, fordi operatoeren tilbod en brugbar omlaegning.' => 'Les frais alternatifs personnels ne sont normalement pas couverts, car l operateur a propose un reacheminement utilisable.',
        'Den naede ikke mit endelige bestemmelsessted' => 'Il n atteignait pas ma destination finale',
        'Den afgik for sent / gav urimelig samlet forsinkelse' => 'Il partait trop tard / entrainait un retard total deraisonnable',
        'Den kraevede urimelige ekstra skift' => 'Il exigeait des correspondances supplementaires deraisonnables',
        'Den var ikke tilgaengelig for mig / PMR' => 'Il n etait pas accessible pour moi / PMR',
        'Den var ikke mulig med cykel/bagage/reservation' => 'Il n etait pas possible avec velo, bagages ou reservation',
        'Den blev ikke faktisk stillet til raadighed' => 'Il n a pas reellement ete mis a disposition',
        'Hvordan blev den senere rejse haandteret?' => 'Comment le voyage ulterieur a-t-il ete gere ?',
        'Jeg har endnu ikke booket ny rejse' => 'Je n ai pas encore reserve de nouveau voyage',
        'Ingen senere rejse booket endnu' => 'Aucun voyage ulterieur reserve pour le moment',
        'Du har valgt omlaegning senere, men har endnu ikke booket ny rejse. Du kan tilfoje dokumentation senere, hvis der opstaar udgifter.' => 'Vous avez choisi un reacheminement ulterieur, mais vous n avez pas encore reserve de nouveau voyage. Vous pourrez ajouter les justificatifs plus tard si des frais surviennent.',
        'Klik for at hente forslag til omlaegning (TRANSIT). Vi sender start/destination til Google.' => 'Cliquez pour recuperer des suggestions de reacheminement (TRANSIT). Nous envoyons le depart et la destination a Google.',
        'Tip: Brug missed connection / din nuvaerende station som start.' => 'Conseil : utilisez la correspondance manquee ou votre gare actuelle comme point de depart.',
        'Angiv destination (ticketless/ukendt destination).' => 'Indiquez la destination (sans billet / destination inconnue).',
        'TRIN 6 er uafhaengigt af TRIN 5. Brug evt. stationen fra Art.20 som udgangspunkt.' => 'L etape 6 est independante de l etape 5. Utilisez eventuellement la gare de l art. 20 comme point de depart.',
        'Stationsvalg (omlaegning)' => 'Choix des gares (reacheminement)',
        'Transportform (omlaegning)' => 'Mode de transport (reacheminement)',
        'Hvor endte omlaegningen?' => 'Ou le reacheminement vous a-t-il mene ?',
        'Et andet egnet afgangssted' => 'Un autre lieu de depart approprie',
        'Mit endelige bestemmelsessted' => 'Ma destination finale',
        'Koebte du selv en ny billet for at komme videre?' => 'Avez-vous achete vous-meme un nouveau billet pour poursuivre le voyage ?',
        'Hvorfor koebte du selv?' => 'Pourquoi avez-vous achete vous-meme ?',
        'Ingen tilbudt omlaegning' => 'Aucun reacheminement propose',
        'Flyselskabets loesning kunne ikke bruges' => 'La solution de la compagnie aerienne ne pouvait pas etre utilisee',
        'Skulle hurtigt videre' => 'Je devais poursuivre rapidement',
        'Du koebte selv ny billet, selvom omlaegning blev tilbudt inden for 100 min - udgiften refunderes normalt ikke (Art. 18(3)). Kompensation kan stadig vaere mulig.' => 'Vous avez achete vous-meme un nouveau billet alors qu un reacheminement avait ete propose dans les 100 minutes. Cette depense n est normalement pas remboursee (art. 18(3)). Une indemnisation peut toutefois rester possible.',
        'Rail gemmer ikke billetter eller kvitteringer her. Hvis du selv maatte finde en senere forbindelse, laegges billet, beloeb og dokumentation paa backend-sagen.' => 'Rail n enregistre pas les billets ou justificatifs ici. Si vous avez du trouver vous-meme une correspondance ulterieure, le billet, le montant et les documents seront ajoutes au dossier back-office.',
        'Medfoerte omlaegningen ekstra udgifter for dig?' => 'Le reacheminement a-t-il entraine des frais supplementaires pour vous ?',
        'Type af omlaegningsudgift' => 'Type de frais de reacheminement',
        'Hvilken type udgift havde du?' => 'Quel type de frais avez-vous eu ?',
        'Typisk acceptable poster er noedvendig billet, reservation, transfer eller anden relevant omlaegningsudgift. Maaltider og hotel hoerer stadig til under assistance.' => 'Les postes typiquement acceptables sont le billet necessaire, la reservation, le transfert ou un autre frais de reacheminement pertinent. Les repas et l hotel relevent toujours de l assistance.',
        'Vaelg den mest relevante udgiftstype for den omlaegning, som operatoeren stod for. Beloeb, valuta, forklaring og kvitteringer registreres senere paa sagen.' => 'Choisissez le type de frais le plus pertinent pour le reacheminement pris en charge par l operateur. Le montant, la devise, l explication et les justificatifs seront ajoutes plus tard au dossier.',
        'Vaelg den mest relevante udgiftstype for din egen omlaegning. Beloeb, valuta, forklaring og kvitteringer registreres senere paa sagen.' => 'Choisissez le type de frais le plus pertinent pour votre propre reacheminement. Le montant, la devise, l explication et les justificatifs seront ajoutes plus tard au dossier.',
        'Vaelg den mest relevante udgiftstype for din egen senere omlaegning. Beloeb, valuta, forklaring og kvitteringer registreres senere paa sagen.' => 'Choisissez le type de frais le plus pertinent pour votre propre reacheminement ulterieur. Le montant, la devise, l explication et les justificatifs seront ajoutes plus tard au dossier.',
        'Billet eller tillaeg til den omlagte rejse' => 'Billet ou supplement pour le voyage reachemine',
        'Hojere klasse eller obligatorisk supplement' => 'Classe superieure ou supplement obligatoire',
        'Pladsreservation / saede-, ligge- eller sovepladstillaeg' => 'Reservation de place / supplement siege, couchette ou lit',
        'Alternativ transport anvist af operatoeren' => 'Transport alternatif indique par l operateur',
        'Transfer mellem stationer eller afgangssteder' => 'Transfert entre gares ou lieux de depart',
        'Andet nodvendigt udlaeg' => 'Autre depense necessaire',
        'Ny togbillet' => 'Nouveau billet de train',
        'Ny bus-/turistbusbillet' => 'Nouveau billet de bus / autocar',
        'Pladsreservation eller obligatorisk tillaeg' => 'Reservation de place ou supplement obligatoire',
        'Hojere klasse, fordi tilsvarende/lavere klasse ikke var tilgaengelig' => 'Classe superieure car une classe equivalente ou inferieure n etait pas disponible',
        'Nodvendig transfer til ny station eller nyt afgangssted' => 'Transfert necessaire vers une nouvelle gare ou un nouveau lieu de depart',
        'Taxi/rideshare, hvis ingen rimelig offentlig transportlosning var mulig' => 'Taxi / covoiturage si aucune solution raisonnable de transport public n etait possible',
        'Soeg destination' => 'Rechercher une destination',
        'Note: Din selvbetalte transport i TRIN 5 ligner returtransport. Overvej om refusion/returtransport passer bedre.' => 'Note : votre transport paye vous-meme a l etape 5 ressemble a un transport de retour. Verifiez si le remboursement / transport de retour convient mieux.',
        'Note: Din selvbetalte transport i TRIN 5 ligner videre rejse mod destination. Overvej om omlaegning passer bedre.' => 'Note : votre transport paye vous-meme a l etape 5 ressemble a une poursuite vers la destination. Verifiez si le reacheminement convient mieux.',
        'Note: Din selvbetalte transport i TRIN 5 ligner hotel/overnatning. Det vurderes typisk under assistance (Trin 7).' => 'Note : votre transport paye vous-meme a l etape 5 ressemble a un hotel / hebergement. Cela releve generalement de l assistance (etape 7).',
        'Remboursement valgt' => 'Remboursement selectionne',
        'Refusion valgt' => 'Remboursement selectionne',
        'Aucun vrai choix tilbudt' => 'Aucun vrai choix propose',
        'Vi viser nu spoergsmaal om den foerste brugbare loesning og eventuel videre rejse.' => 'Nous affichons maintenant les questions sur la premiere solution utilisable et la poursuite eventuelle du voyage.',
        'Vi viser nu spoergsmaal om ombooking, fordi transportoeren ikke gav et reelt valg mellem mulighederne.' => 'Nous affichons maintenant les questions sur le reacheminement, car le transporteur n a pas donne de choix reel entre les options.',
        'Dette spor handler om den foerste brugbare loesning, som kunne faa dig videre.' => 'Ce parcours concerne la premiere solution utilisable qui pouvait vous permettre de poursuivre.',
        'Dette spor handler om omlaegning paa et senere tidspunkt efter dit eget valg.' => 'Ce parcours concerne un reacheminement a une date ulterieure de votre choix.',
        'Dette spor bruges, naar transportoeren ikke tilbod et reelt valg mellem tilbagebetaling og ombooking.' => 'Ce parcours est utilise lorsque le transporteur n a pas propose de vrai choix entre remboursement et reacheminement.',
        'TRIN 6: Art.18(3) flow aktiv' => 'ETAPE 6 : parcours art. 18(3) actif',
        'TRIN 6: ikke-omlaegning' => 'ETAPE 6 : pas de reacheminement',
        'TRIN 6: bus-omlaegning' => 'ETAPE 6 : reacheminement bus',
        'TRIN 6: air refund/ikke-omlaegning' => 'ETAPE 6 : remboursement air / pas de reacheminement',
        'TRIN 6: afventer om du fortsatte paa den tilbudte alternative flyvning' => 'ETAPE 6 : en attente de savoir si vous avez poursuivi sur le vol alternatif propose',
        'TRIN 6: afventer om du brugte den tilbudte alternative flyvning' => 'ETAPE 6 : en attente de savoir si vous avez utilise le vol alternatif propose',
        'TRIN 6: afventer om du selv maatte finde en anden videre rejse' => 'ETAPE 6 : en attente de savoir si vous avez du trouver vous-meme un autre voyage',
        'TRIN 6: afventer om du selv maatte finde en loesning' => 'ETAPE 6 : en attente de savoir si vous avez du trouver vous-meme une solution',
        'TRIN 6: afventer om du havde reroute-udgifter' => 'ETAPE 6 : en attente de savoir si vous avez eu des frais de reacheminement',
        'TRIN 6: air-omlaegning' => 'ETAPE 6 : reacheminement air',
        'TRIN 6: afventer hvordan rejsen fortsatte' => 'ETAPE 6 : en attente de la maniere dont le voyage a continue',
        'TRIN 6: fortsat rejse paa tilbudt eller accepteret loesning' => 'ETAPE 6 : voyage poursuivi avec une solution proposee ou acceptee',
        'TRIN 6: afventer om operatoeren godkendte din loesning' => 'ETAPE 6 : en attente de l approbation de votre solution par l operateur',
        'TRIN 6: afventer 100-minutters-oplysning' => 'ETAPE 6 : en attente de l information sur les 100 minutes',
        'TRIN 6: afventer om den tilbudte omlaegning stadig var brugbar' => 'ETAPE 6 : en attente de savoir si le reacheminement propose restait utilisable',
        'TRIN 6: egne alternative udgifter er som udgangspunkt blokeret' => 'ETAPE 6 : les frais alternatifs personnels sont en principe bloques',
        'TRIN 6: afventer hvorfor operatoerens loesning ikke kunne bruges' => 'ETAPE 6 : en attente de la raison pour laquelle la solution de l operateur ne pouvait pas etre utilisee',
        'TRIN 6: afventer hvordan den senere rejse blev haandteret' => 'ETAPE 6 : en attente de la maniere dont le voyage ulterieur a ete gere',
        'TRIN 6: senere rejse er ikke booket endnu' => 'ETAPE 6 : le voyage ulterieur n est pas encore reserve',
        'TRIN 6: afventer om der var noedvendige udgifter' => 'ETAPE 6 : en attente de savoir s il y avait des frais necessaires',
        'TRIN 6: afventer hvilken type rail-udgift du havde' => 'ETAPE 6 : en attente du type de frais rail',
        'TRIN 6: rail-omlaegning' => 'ETAPE 6 : reacheminement rail',
        'TRIN 6: afventer omlaegningstilbud' => 'ETAPE 6 : en attente d une offre de reacheminement',
        'TRIN 6: afventer article 8-valg' => 'ETAPE 6 : en attente du choix art. 8',
        'TRIN 6: afventer om du selv maatte arrangere ombooking' => 'ETAPE 6 : en attente de savoir si vous avez du organiser vous-meme le reacheminement',
        'TRIN 6: afventer selvkoeb' => 'ETAPE 6 : en attente de l achat effectue par vous-meme',
        'TRIN 6: afventer hvorfor du koebte selv' => 'ETAPE 6 : en attente de la raison de votre achat',
        'TRIN 6: afventer udfald (senere)' => 'ETAPE 6 : en attente du resultat (plus tard)',
        'TRIN 6: afventer godkendelse' => 'ETAPE 6 : en attente d approbation',
        'TRIN 6: afventer tilbud om videre rejse' => 'ETAPE 6 : en attente d une offre pour poursuivre le voyage',
        'TRIN 6: afventer tidspunkt for foerste brugbare loesning' => 'ETAPE 6 : en attente de l horaire de la premiere solution utilisable',
        'TRIN 6: afventer om operatoeren gav valget' => 'ETAPE 6 : en attente de savoir si l operateur a donne le choix',
        'TRIN 6: afventer 100-min' => 'ETAPE 6 : en attente des 100 min',
        'Havn' => 'Port',
        'Krydstogtterminal' => 'Terminal de croisiere',
        'Station' => 'Gare',
        '(ukendt station)' => '(gare inconnue)',
        '(ukendt terminal)' => '(terminal inconnu)',
        '(ukendt havn)' => '(port inconnu)',
        'Jeg fik refusion' => 'J ai recu un remboursement',
        'Jeg oenskede omlaegning hurtigst muligt' => 'Je souhaitais un reacheminement au plus vite',
        'Jeg oenskede omlaegning paa et senere tidspunkt' => 'Je souhaitais un reacheminement a une date ulterieure',
        'Klik for at hente forslag som reference til den omlaegning, der faktisk blev brugt.' => 'Cliquez pour recuperer des suggestions comme reference pour le reacheminement effectivement utilise.',
        'Omlægningsudgifter registreres i backend' => 'Les frais de reacheminement sont enregistres en back-office',
        'Omlaegningsudgifter registreres i backend' => 'Les frais de reacheminement sont enregistres en back-office',
        'Typisk acceptable poster er ny billet eller noedvendig transfer. Maaltider og hotel hoerer stadig til under assistance.' => 'Les postes typiquement acceptables sont un nouveau billet ou un transfert necessaire. Les repas et l hotel relevent toujours de l assistance.',
        'Jeg fik valget mellem refusion og ombooking' => 'J ai eu le choix entre remboursement et reacheminement',
        'Jeg fik valget mellem tilbagebetaling og ombooking' => 'J ai eu le choix entre remboursement et reacheminement',
        'Kun refusion blev tilbudt' => 'Seul le remboursement a ete propose',
        'Kun tilbagebetaling blev tilbudt' => 'Seul le remboursement a ete propose',
        'Passagerens loesning' => 'Solution du passager',
        'Jeg fik tilbagebetaling' => 'J ai recu un remboursement',
        'Remboursement / retur til afgangshavn eller aftalt udgangspunkt (Art. 18)' => 'Remboursement / retour au port de depart ou au point de depart convenu (art. 18)',
        'Hele billetten' => 'Le billet entier',
        'Havde du udgifter til at komme tilbage til afgangshavnen eller det aftalte udgangspunkt?' => 'Avez-vous eu des frais pour revenir au port de depart ou au point de depart convenu ?',
        'Brug kun dette spor til noedvendig returtransport til afgangshavnen eller det aftalte udgangspunkt. Refusion af selve billetten er 100% og skal ske inden for 7 dage. Internt review-niveau for ekstra returtransport: taxi EUR 150 og alternativ transport samlet EUR 400. Hojere beloeb kan kraeve manuel vurdering.' => 'Utilisez ce parcours uniquement pour le transport de retour necessaire vers le port de depart ou le point de depart convenu. Le remboursement du billet lui-meme est de 100% et doit intervenir dans les 7 jours. Niveau indicatif interne pour le transport de retour supplementaire : taxi 150 EUR et transport alternatif total 400 EUR. Des montants plus eleves peuvent necessiter une verification manuelle.',
        'Transport de retour til afgangshavn/aftalt udgangspunkt' => 'Transport de retour vers le port de depart / point convenu',
        'Ny faerge- eller transportbillet' => 'Nouveau billet de ferry ou de transport',
        'Endeligt valg baseret paa hvad der skete med faergerejsen.' => 'Choix final base sur ce qui est arrive pendant la traversee.',
        'Operatøren tilbød senere omlægning' => 'L operateur a ensuite propose un reacheminement',
        'Operatoeren tilbod senere omlaegning' => 'L operateur a ensuite propose un reacheminement',
        'Jeg købte selv en billet til senere' => 'J ai achete moi-meme un billet pour plus tard',
        'Jeg koebte selv en billet til senere' => 'J ai achete moi-meme un billet pour plus tard',
        'Hvis du købte ny billet til senere: angiv beløb og upload billetten.' => 'Si vous avez achete un nouveau billet pour plus tard : indiquez le montant et televersez le billet.',
        'Hvis du koebte ny billet til senere: angiv beloeb og upload billetten.' => 'Si vous avez achete un nouveau billet pour plus tard : indiquez le montant et televersez le billet.',
        'Billet/kvittering' => 'Billet / justificatif',
        'Registrer kun ombookingsnaere udgifter som ny faerge-/transportbillet og noedvendig transfer. Maaltider, hotel og terminal-hoteltransport hoerer til under assistance. Internt review-niveau: taxi EUR 150 og alternativ transport samlet EUR 400. Hojere beloeb kan kraeve manuel vurdering.' => 'Enregistrez uniquement les frais lies au reacheminement, comme un nouveau billet de ferry / transport et un transfert necessaire. Les repas, l hotel et le transport terminal-hotel relevent de l assistance. Niveau indicatif interne : taxi 150 EUR et transport alternatif total 400 EUR. Des montants plus eleves peuvent necessiter une verification manuelle.',
        'Typiske poster er ny billet eller noedvendig transfer. Maaltider, hotel og terminal-hoteltransport hoerer til under assistance.' => 'Les postes typiques sont un nouveau billet ou un transfert necessaire. Les repas, l hotel et le transport terminal-hotel relevent de l assistance.',
        '50% af billetpris' => '50% du prix du billet',
        'Bruges som hjaelp til at rekonstruere returtransporten fra det sted, hvor du stod, til det sted du skulle tilbage til.' => 'Sert a reconstruire le transport de retour depuis l endroit ou vous etiez bloque vers le lieu ou vous deviez revenir.',
        'Hvilken lufthavn skal du tilbage til?' => 'Vers quel aeroport devez-vous revenir ?',
        'Brug kun dette spor til noedvendig returtransport til udgangspunktet. Vejledende review-niveau: returflyvning 100-1500 EUR og bynaer transfer 20-150 EUR og transfer mellem lufthavne 40-300 EUR og tog/bus 20-300 EUR og taxi/rideshare 30-200 EUR. EU261 har ikke et fast lovbestemt cap, men udgifter over standardniveau kan kraeve manuel vurdering. Vi har estimeret ud fra den mest sandsynlige lufthavn. Du kan rette placeringen senere, hvis den ikke passer.' => 'Utilisez ce parcours uniquement pour le transport de retour necessaire vers le point de depart. Niveau indicatif : vol retour 100-1500 EUR, transfert urbain 20-150 EUR, transfert entre aeroports 40-300 EUR, train/bus 20-300 EUR et taxi/rideshare 30-200 EUR. Le reglement UE 261 ne prevoit pas de plafond fixe, mais les frais au-dessus du niveau standard peuvent necessiter une verification manuelle. Nous avons estime selon l aeroport le plus probable ; vous pourrez corriger le lieu plus tard si necessaire.',
        'Refusionsnaere udgiftsposter' => 'Postes de frais proches du remboursement',
        'Tilfoej en eller flere poster, hvis retur til udgangspunkt eller forste afgangssted medfoerte flere udgifter.' => 'Ajoutez un ou plusieurs postes si le retour au point de depart ou au premier lieu de depart a entraine des frais supplementaires.',
        'Registrer kun ombookingsnaere udgifter som ny billet og noedvendig transfer. Maaltider og hotel hoerer til under assistance. Internt review-niveau: bynaer transfer 20-150 EUR · lufthavnsskift 40-300 EUR · tog/bus 20-300 EUR · taxi/rideshare 30-200 EUR · selvbetalt ny flyvning regionalt 100-1500 EUR. EU261 har ikke et fast lovbestemt cap, men dyrere loesninger kan blive sendt til manuel vurdering. Vi har estimeret ud fra den mest sandsynlige lufthavn. Du kan rette placeringen senere, hvis den ikke passer.' => 'Enregistrez uniquement les frais proches du reacheminement, comme un nouveau billet et un transfert necessaire. Les repas et l hotel relevent de l assistance. Niveau indicatif interne : transfert urbain 20-150 EUR, changement d aeroport 40-300 EUR, train/bus 20-300 EUR, taxi/rideshare 30-200 EUR, nouveau vol regional paye par vous-meme 100-1500 EUR. Le reglement UE 261 ne prevoit pas de plafond fixe, mais les solutions plus couteuses peuvent etre envoyees en verification manuelle. Nous avons estime selon l aeroport le plus probable ; vous pourrez corriger le lieu plus tard si necessaire.',
        'Ny billet' => 'Nouveau billet',
        'Du kan registrere flere billetter, transfers eller andre noedvendige ombookingsudgifter.' => 'Vous pouvez enregistrer plusieurs billets, transferts ou autres frais necessaires de reacheminement.',
    ],
];
$translateRemedy = static function ($text) use ($uiLanguage, $remediesTranslations) {
    if (!is_string($text)) {
        return $text;
    }

    if (isset($remediesTranslations[$uiLanguage][$text])) {
        return $remediesTranslations[$uiLanguage][$text];
    }

    $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return $remediesTranslations[$uiLanguage][$decoded] ?? $text;
};

if ($transportMode === 'ferry') {
    $steps = [
        1 => 'Billet, grunddata',
        2 => 'Vaelg afgang',
        3 => 'Haendelse',
        4 => 'Valg efter haendelsen',
        5 => 'Refusion, ombooking',
        6 => 'Assistance',
        7 => 'Kontakt, opret sag',
    ];
    $currentStep = 5;
    $brandName = 'FerryClaim';
    $brandMark = 'FC';
    $subtitle = '';
} elseif ($transportMode === 'air') {
    $steps = [
        1 => 'Billet, grunddata',
        2 => 'Vaelg fly',
        3 => 'Haendelse',
        4 => 'Valg efter haendelsen',
        5 => 'Refund, ombooking',
        6 => 'Assistance',
        7 => 'Nedgradering',
        8 => 'Kontakt, opret sag',
    ];
    $currentStep = 5;
    $brandName = 'AirClaim';
    $brandMark = 'AC';
    $subtitle = 'Afklar om du har krav paa refund, ombooking eller videre assistance efter haendelsen.';
} else {
    $steps = [
        1 => 'Start & Rejsestatus',
        2 => 'Billet / Ticketless + Grunddata',
        3 => 'Haendelseskede + gating',
        4 => 'Refusion / Omlaegning (Art. 18)',
        5 => 'Beregning & Resultat (Art. 19)',
        6 => 'Ansoeger & Udbetaling',
        7 => 'Samtykke & Ekstra info',
    ];
    $currentStep = 4;
    $brandName = 'TrainClaim';
    $brandMark = 'TC';
    $subtitle = '';
}
$steps = array_map($translateRemedy, $steps);
if ($subtitle !== '') {
    $subtitle = $translateRemedy($subtitle);
}

$doneSteps = $doneSteps ?? [];
$flowQuery = ['tc6' => 1];
if ($uiLanguage !== 'da') {
    $flowQuery['lang'] = $uiLanguage;
}
$progressPct = (int)($progressPct ?? (count($steps) > 0 ? round(($currentStep / count($steps)) * 100) : 0));
$progressLabel = (string)($progressLabel ?? ($currentStep . ' / ' . count($steps) . ($uiLanguage === 'fr' ? ' etapes' : ' trin')));
$context = match ($travelState) {
    'ongoing' => $translateRemedy('Igangvaerende rejse'),
    'before_start' => $translateRemedy('Foer afgang'),
    default => $translateRemedy('Afsluttet rejse'),
};
$stats = $stats ?? [
    [$translateRemedy('Flow'), $context, null],
];

$completedRemedyChoice = trim((string)($form['rail_completed_remedy_choice'] ?? ''));
$shouldSeedCompletedRemedyChoice = $transportMode === 'rail'
    && $travelState === 'completed'
    && in_array($completedRemedyChoice, ['refund_return', 'reroute_soonest', 'reroute_later'], true);
if ($shouldSeedCompletedRemedyChoice) {
    $form['remedyChoice'] = $completedRemedyChoice;
}

$remedyChoice = trim((string)($form['remedyChoice'] ?? ''));
$livePlanChoice = trim((string)($form['rail_live_plan_choice'] ?? ''));
$shouldSeedLiveRemedyChoice = $travelState === 'ongoing'
    && $remedyChoice === ''
    && in_array($livePlanChoice, ['refund_return', 'reroute_soonest', 'reroute_later'], true);
if ($shouldSeedLiveRemedyChoice) {
    $form['remedyChoice'] = $livePlanChoice;
    $remedyChoice = $livePlanChoice;
}
$remedyPreviewChoice = $remedyChoice !== '' ? $remedyChoice : $livePlanChoice;
$remedySummary = match ($remedyPreviewChoice) {
    'refund_return' => $translateRemedy('Tilbagebetaling'),
    'reroute_soonest' => $translateRemedy('Ombooking hurtigst muligt'),
    'reroute_later' => $translateRemedy('Ombooking senere'),
    'no_refund_continue' => $translateRemedy('Fortsat rejse'),
    'no_real_choice' => $translateRemedy('Intet reelt valg'),
    default => $translateRemedy('Afventer'),
};
$remedySummaryBadge = $remedyPreviewChoice !== '' ? 'blue' : 'gray';

$assistanceActive = false;
foreach ([
    'gate_art20',
    'gate_ferry_art17_refreshments',
    'gate_ferry_art17_hotel',
    'gate_ferry_pmr_assistance',
    'gate_ferry_pmr_assistance_partial',
    'gate_bus_assistance_refreshments',
    'gate_bus_assistance_hotel',
    'gate_bus_pmr_assistance',
    'gate_bus_pmr_assistance_partial',
] as $flagKey) {
    if ((string)($flags[$flagKey] ?? '') === '1') {
        $assistanceActive = true;
        break;
    }
}
$assistanceSummary = $translateRemedy($assistanceActive ? 'Aktiv' : 'Afventer');
$assistanceSummaryBadge = $assistanceActive ? 'green' : 'gray';

$operatorLabel = '';
foreach ([
    $form['operator'] ?? null,
    $meta['_auto']['operator']['value'] ?? null,
    $meta['rail_selected_departure']['operator'] ?? null,
    $form['operating_carrier'] ?? null,
    $form['marketing_carrier'] ?? null,
] as $candidateOperator) {
    $candidateOperator = trim((string)$candidateOperator);
    if ($candidateOperator !== '') {
        $operatorLabel = $candidateOperator;
        break;
    }
}
if ($operatorLabel === '') {
    $operatorLabel = $translateRemedy('Ikke valgt endnu');
}

$tc6Imp1PillRadios = true;
$tc6Imp2HideTrin6 = true;
$tc6Imp3BadgeColors = true;
$tc6Imp4SidebarIcons = true;

if ($tc6Imp3BadgeColors) {
    $remedySummaryBadge = $remedyPreviewChoice !== '' ? 'blue' : 'amber';
    $assistanceSummaryBadge = $assistanceActive ? 'green' : 'amber';
}

$summaryRows = [
    [$translateRemedy('Refusion / ombooking'), $remedySummary, $remedySummaryBadge],
    [$translateRemedy('Assistance'), $assistanceSummary, $assistanceSummaryBadge],
    [$translateRemedy('Operatoer'), $operatorLabel, null],
];

ob_start();
$isTc6Preview = true;
require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'remedies.php';
$legacyContent = (string)ob_get_clean();
$legacyContent = str_replace(
    'action="/rail_app/flow/remedies"',
    'action="' . h(html_entity_decode($this->Url->build(['action' => 'remedies', '?' => $flowQuery]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) . '"',
    $legacyContent
);

ob_start();
echo $this->element('tc6/action_bar', [
    'backUrl' => $this->Url->build(['action' => $remediesPrevAction ?? 'incident', '?' => $flowQuery]),
    'backLabel' => $translateRemedy('Tilbage'),
    'nextLabel' => $translateRemedy('Naeste trin'),
    'nextVariant' => 'navy',
    'submitName' => 'continue',
]);
$actionBarHtml = (string)ob_get_clean();
$legacyContent = preg_replace('/<div class="fps-actions">.*?<\/div>\s*<\/fieldset>/s', $actionBarHtml . '</fieldset>', $legacyContent, 1) ?? $legacyContent;
if ($uiLanguage !== 'da' && isset($remediesTranslations[$uiLanguage])) {
    $legacyContent = strtr($legacyContent, $remediesTranslations[$uiLanguage]);
    if ($uiLanguage === 'fr') {
        $legacyContent = str_replace(
            [
                'V&aelig;lg',
                'K&oslash;bte du selv en ny billet for at komme videre?',
                'Du k&oslash;bte selv ny billet, selvom oml&aelig;gning blev tilbudt inden for 100 min - udgiften refunderes normalt ikke (Art. 18(3)). Kompensation kan stadig v&aelig;re mulig.',
                'Hvilken station er du p&aring;?',
                'Anden station',
                'Ved ikke',
                'Naermeste station',
                'Hvilken station skal du tilbage til?',
                'Hvilken station omlagde du til?',
                'Hvordan vil du fortsaette rejsen?',
                'Jeg oensker refusion',
                'Jeg oensker omlaegning hurtigst muligt',
                'Jeg oensker omlaegning senere (efter eget valg)',
                'Bruges som hjaelp til at finde ruter for returtransport (fra din nuvaerende station til stationen du skal tilbage til).',
                'Brug Google Maps i denne sag',
                'Fra (station)',
                'Til (destination)',
                'Refusionsnaer udgiftstype',
                'Vaelg den naermeste kategori. Beloeb, valuta og kvitteringer registreres senere i backend.',
                'Kunne den tilbudte omlaegning bruges?',
                'Hvorfor kunne den tilbudte omlaegning ikke bruges?',
                'Klik for at hente forslag til omlaegning (TRANSIT). Vi sender start/destination til Google.',
                'Tip: Brug missed connection / din nuvaerende station som start.',
                'Angiv destination (ticketless/ukendt destination).',
                'TRIN 6 er uafhaengigt af TRIN 5. Brug evt. stationen fra Art.20 som udgangspunkt.',
                'Rail gemmer ikke billetter eller kvitteringer her. Hvis du selv maatte finde en senere forbindelse, laegges billet, beloeb og dokumentation paa backend-sagen.',
                'Hvorfor k&oslash;bte du selv?',
                'Ingen tilbudt oml&aelig;gning',
                'Havde du ekstraudgifter som foelge af omlaegning eller tilbagevenden til udgangspunktet?',
                'Transport de retour / tilbage til afgangssted',
                'Ja',
                'Nej',
                'Andet',
                'VIDEREFOERELSE',
                'OMLAEGNING AF TRANSPORTOER',
                'EGEN OMLAEGNING',
                'Jeg fortsatte med samme tog',
                'Jeg fortsatte med naeste mulige tog paa samme rute',
                'Operateuren lod mig bruge min billet paa en anden afgang',
                'Operateuren omlagde mig via anden rute/andet tog',
                'Operateuren tilbod bus/taxi/anden transport',
                'Jeg kobte selv ny billet/transport',
                'Operateuren aendrede/ombookede min billet',
            ],
            [
                'Choisir',
                'Avez-vous achete vous-meme un nouveau billet pour poursuivre le voyage ?',
                'Vous avez achete vous-meme un nouveau billet alors qu un reacheminement avait ete propose dans les 100 minutes. Cette depense n est normalement pas remboursee (art. 18(3)). Une indemnisation peut toutefois rester possible.',
                'Dans quelle gare etes-vous ?',
                'Autre gare',
                'Je ne sais pas',
                'Gare la plus proche',
                'Vers quelle gare devez-vous retourner ?',
                'Vers quelle gare avez-vous ete reachemine ?',
                'Comment souhaitez-vous poursuivre le voyage ?',
                'Je souhaite un remboursement',
                'Je souhaite un reacheminement au plus vite',
                'Je souhaite un reacheminement plus tard (a mon choix)',
                'Sert a trouver des itineraires pour le transport de retour, depuis votre gare actuelle vers la gare ou vous devez revenir.',
                'Utiliser Google Maps dans ce dossier',
                'Depuis (gare)',
                'Vers (destination)',
                'Type de frais proche du remboursement',
                'Choisissez la categorie la plus proche. Le montant, la devise et les justificatifs seront ajoutes plus tard dans le back-office.',
                'Le reacheminement propose pouvait-il etre utilise ?',
                'Pourquoi le reacheminement propose ne pouvait-il pas etre utilise ?',
                'Cliquez pour recuperer des suggestions de reacheminement (TRANSIT). Nous envoyons le depart et la destination a Google.',
                'Conseil : utilisez la correspondance manquee ou votre gare actuelle comme point de depart.',
                'Indiquez la destination (sans billet / destination inconnue).',
                'L etape 6 est independante de l etape 5. Utilisez eventuellement la gare de l art. 20 comme point de depart.',
                'Rail n enregistre pas les billets ou justificatifs ici. Si vous avez du trouver vous-meme une correspondance ulterieure, le billet, le montant et les documents seront ajoutes au dossier back-office.',
                'Pourquoi avez-vous achete vous-meme ?',
                'Aucun reacheminement propose',
                'Avez-vous eu des frais supplementaires en raison du reacheminement ou du retour au point de depart ?',
                'Transport de retour / retour au lieu de depart',
                'Oui',
                'Non',
                'Autre',
                'POURSUITE DU VOYAGE',
                'REACHEMINEMENT PAR L OPERATEUR',
                'REACHEMINEMENT PAR MOI-MEME',
                'J ai poursuivi avec le meme train',
                'J ai poursuivi avec le prochain train possible sur le meme itineraire',
                'L operateur m a laisse utiliser mon billet sur un autre depart',
                'L operateur m a reroute via un autre itineraire / un autre train',
                'L operateur a propose un bus, un taxi ou un autre transport',
                'J ai achete moi-meme un nouveau billet / transport',
                'L operateur a modifie / rebooke mon billet',
            ],
            $legacyContent
        );
        $legacyContent = str_replace(
            [
                'Hvilken station skulle du tilbage til?',
                'Oml&aelig;gningsudgifter registreres i backend',
                'Omlægningsudgifter registreres i backend',
                'Omlaegningsudgifter registreres i backend',
                'Remboursement / retur til afgangshavn eller aftalt udgangspunkt (Art. 18)',
                'Transport de retour til afgangshavn/aftalt udgangspunkt',
                'Operat&oslash;ren tilb&oslash;d senere oml&aelig;gning',
                'Operatøren tilbød senere omlægning',
                'Operatoeren tilbod senere omlaegning',
            ],
            [
                'Vers quelle gare deviez-vous retourner ?',
                'Les frais de reacheminement sont enregistres en back-office',
                'Les frais de reacheminement sont enregistres en back-office',
                'Les frais de reacheminement sont enregistres en back-office',
                'Remboursement / retour au port de depart ou au point de depart convenu (art. 18)',
                'Transport de retour vers le port de depart / point convenu',
                'L operateur a ensuite propose un reacheminement',
                'L operateur a ensuite propose un reacheminement',
                'L operateur a ensuite propose un reacheminement',
            ],
            $legacyContent
        );
    }
}

if ($tc6Imp1PillRadios) {
    $remediesCssPath = WWW_ROOT . 'css' . DS . 'tc6' . DS . 'remedies.css';
    $remediesCssVersion = is_file($remediesCssPath) ? filemtime($remediesCssPath) : time();
    $this->start('css');
    echo '<link rel="stylesheet" href="' . h($this->Url->build('/css/tc6/remedies.css?v=' . $remediesCssVersion)) . '">';
    $this->end();
}

$railRemedyVisibilityCss = '';
if ($transportMode === 'rail' && $railEnabledRemedies !== []) {
    $hideSelectors = [];
    foreach ([
        'refund_return' => 'refund_return',
        'reroute_soonest' => 'reroute_soonest',
        'reroute_later' => 'reroute_later',
    ] as $key => $value) {
        if (empty($railEnabledRemedies[$key])) {
            $hideSelectors[] = '.tc6-remedies-wrap label:has(input[name="remedyChoice"][value="' . $value . '"])';
        }
    }
    if ($hideSelectors !== []) {
        $railRemedyVisibilityCss = '<style>' . implode(',', $hideSelectors) . '{display:none !important;}</style>';
    }
}

$ferryRemedyVisibilityCss = '';
if ($transportMode === 'ferry' && in_array($remedyPreviewChoice, ['refund_return', 'reroute_soonest'], true)) {
    $hideSelectors = [];
    foreach ([
        'refund_return',
        'reroute_soonest',
    ] as $value) {
        if ($value === $remedyPreviewChoice) {
            continue;
        }
        $hideSelectors[] = '.tc6-remedies-wrap label:has(input[name="remedyChoice"][value="' . $value . '"])';
    }
    if ($hideSelectors !== []) {
        $ferryRemedyVisibilityCss = '<style>' . implode(',', $hideSelectors) . '{display:none !important;}</style>';
    }
}

$transportModeCss = '';
if ($transportMode === 'ferry') {
    $transportModeCss = '<style>
        .tc6-remedies-wrap > .tc6-subtitle,
        .tc6-remedies-wrap .card > .small.muted,
        .tc6-remedies-wrap .fps-panel > .small.muted,
        .tc6-remedies-wrap .small.muted.mt4,
        .tc6-remedies-wrap .small.muted.mt8,
        .tc6-remedies-wrap .small.muted.ml8 {
            display: none !important;
        }
        .tc6-remedies-wrap [hidden],
        .tc6-remedies-wrap .hidden {
            display: none !important;
        }
        .tc6-remedies-wrap .mt4:not([hidden]):not(.hidden):has(> label > input[type="radio"]),
        .tc6-remedies-wrap .mt8:not([hidden]):not(.hidden):has(> label > input[type="radio"]),
        .tc6-remedies-wrap .mt12:not([hidden]):not(.hidden):has(> label > input[type="radio"]) {
            display: flex !important;
            flex-wrap: wrap !important;
            gap: 12px !important;
            align-items: stretch !important;
        }
        .tc6-remedies-wrap .mt4:not([hidden]):not(.hidden):has(> label > input[type="radio"]) > :first-child,
        .tc6-remedies-wrap .mt8:not([hidden]):not(.hidden):has(> label > input[type="radio"]) > :first-child,
        .tc6-remedies-wrap .mt12:not([hidden]):not(.hidden):has(> label > input[type="radio"]) > :first-child {
            width: 100% !important;
            margin: 0 !important;
        }
        .tc6-remedies-wrap .mt4 > label:has(input[type="radio"]),
        .tc6-remedies-wrap .mt8 > label:has(input[type="radio"]),
        .tc6-remedies-wrap .mt12 > label:has(input[type="radio"]) {
            display: inline-flex !important;
            align-items: center !important;
            gap: 12px !important;
            min-height: 64px !important;
            padding: 14px 16px !important;
            margin: 0 !important;
            border-radius: var(--tc-r-lg) !important;
            border: 1.5px solid var(--tc-border-md) !important;
            background: var(--tc-surface) !important;
            box-shadow: var(--tc-shadow-xs) !important;
            box-sizing: border-box !important;
            cursor: pointer !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            color: var(--tc-text-1) !important;
            width: calc(50% - 6px) !important;
            min-width: 180px !important;
            flex: 1 1 180px !important;
            justify-content: flex-start !important;
        }
        .tc6-remedies-wrap .mt4 > label.ml8:has(input[type="radio"]),
        .tc6-remedies-wrap .mt8 > label.ml8:has(input[type="radio"]),
        .tc6-remedies-wrap .mt12 > label.ml8:has(input[type="radio"]) {
            margin-left: 0 !important;
        }
        .tc6-remedies-wrap .mt4 > label:has(input[type="radio"]):has(input:checked),
        .tc6-remedies-wrap .mt8 > label:has(input[type="radio"]):has(input:checked),
        .tc6-remedies-wrap .mt12 > label:has(input[type="radio"]):has(input:checked) {
            border-color: var(--tc-blue) !important;
            background: #f0f7ff !important;
            color: var(--tc-blue) !important;
            box-shadow: 0 0 0 3px rgba(29,111,216,0.08), var(--tc-shadow-sm) !important;
        }
        .tc6-remedies-wrap .mt4 > label:has(input[type="radio"]) input[type="radio"],
        .tc6-remedies-wrap .mt8 > label:has(input[type="radio"]) input[type="radio"],
        .tc6-remedies-wrap .mt12 > label:has(input[type="radio"]) input[type="radio"] {
            margin: 0 !important;
            width: 16px !important;
            height: 16px !important;
            accent-color: #2563eb !important;
        }
        .tc6-remedies-wrap #returnExpensePast:not([hidden]):not(.hidden),
        .tc6-remedies-wrap #returnExpenseNow:not([hidden]):not(.hidden) {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 12px !important;
            align-items: stretch !important;
        }
        .tc6-remedies-wrap #returnExpensePast > .card-title,
        .tc6-remedies-wrap #returnExpenseNow > .card-title,
        .tc6-remedies-wrap #returnExpensePast > .card,
        .tc6-remedies-wrap #returnExpenseNow > .card,
        .tc6-remedies-wrap #returnExpensePast > .ferry-refund-scope-card,
        .tc6-remedies-wrap #returnExpenseNow > .ferry-refund-scope-card {
            grid-column: 1 / -1 !important;
            width: 100% !important;
            margin: 0 !important;
        }
        .tc6-remedies-wrap #returnExpensePast > .mt4,
        .tc6-remedies-wrap #returnExpenseNow > .mt4,
        .tc6-remedies-wrap #returnExpensePast > .fps-callout,
        .tc6-remedies-wrap #returnExpenseNow > .fps-callout,
        .tc6-remedies-wrap #returnExpenseFieldsPast,
        .tc6-remedies-wrap #returnExpenseFieldsNow {
            grid-column: 1 / -1 !important;
            width: 100% !important;
            margin: 0 !important;
        }
        .tc6-remedies-wrap #returnExpensePast > .mt4,
        .tc6-remedies-wrap #returnExpenseNow > .mt4 {
            order: 1 !important;
        }
        .tc6-remedies-wrap #returnExpensePast > label:has(input[type="radio"]),
        .tc6-remedies-wrap #returnExpenseNow > label:has(input[type="radio"]) {
            order: 2 !important;
            grid-column: auto !important;
            width: 100% !important;
            min-width: 0 !important;
            flex: initial !important;
        }
        .tc6-remedies-wrap #returnExpenseFieldsPast,
        .tc6-remedies-wrap #returnExpenseFieldsNow {
            order: 3 !important;
        }
        .tc6-remedies-wrap .ferry-refund-scope-card {
            display: grid !important;
            grid-template-columns: 1fr !important;
            gap: 8px !important;
        }
        .tc6-remedies-wrap .ferry-refund-scope-card > label {
            display: block !important;
            width: 100% !important;
            margin: 0 !important;
        }
        .tc6-remedies-wrap .ferry-refund-scope-card select {
            width: 100% !important;
        }
        @media (max-width: 760px) {
            .tc6-remedies-wrap #returnExpensePast,
            .tc6-remedies-wrap #returnExpenseNow {
                grid-template-columns: 1fr !important;
            }
        }
    </style>';
}

$content = '<div class="tc6-remedies-wrap">'
    . $transportModeCss
    . $railRemedyVisibilityCss
    . $ferryRemedyVisibilityCss
    . '<div class="tc6-chip">' . h($translateRemedy('Trin ')) . (int)$currentStep . ' / ' . count($steps) . '</div>'
    . ($subtitle !== '' ? '<p class="tc6-subtitle">' . h($subtitle) . '</p>' : '')
    . $legacyContent
    . '</div>';

ob_start();
echo $this->element('tc6/live_estimate_router', [
    'form' => $form,
    'flags' => $flags,
    'meta' => $meta,
    'journey' => $journey ?? [],
]);
$liveEstimateHtml = trim((string)ob_get_clean());

$rightPanel = $this->element('tc6/right_rail_panel', [
    'compensation' => $compensation ?? null,
    'compRate' => $compRate ?? null,
    'compBase' => $compBase ?? null,
    'progressPct' => $progressPct,
    'progressLabel' => $progressLabel,
    'stats' => $stats,
    'summaryRows' => $summaryRows,
    'nextHint' => '',
    'liveEstimateHtml' => $liveEstimateHtml,
    'uiLanguage' => $uiLanguage,
]);

echo $this->element('tc6/shell', compact(
    'steps',
    'currentStep',
    'doneSteps',
    'content',
    'rightPanel',
    'context',
    'brandName',
    'brandMark'
) + ['showLockIcons' => $tc6Imp4SidebarIcons]);
