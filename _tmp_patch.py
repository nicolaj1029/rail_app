from pathlib import Path
p=Path('templates/Flow/choices.php')
text=p.read_text(encoding='utf-8',errors='replace')
text=text.replace('TRIN 4 �� Dine valg (Art. 18)','TRIN 4 - Dine valg (Art. 18)')
insert = "<?php\n    if ($travelState === 'completed') {\n        echo '<p class=\"small muted\">Status: Rejsen er afsluttet. Besvar ud fra hvad der faktisk skete.</p>';\n    } elseif ($travelState === 'ongoing') {\n        echo '<p class=\"small muted\">Status: Rejsen er i gang. Vi samler dine valg for resten af forløbet.</p>';\n    } elseif ($travelState === 'not_started') {\n        echo '<p class=\"small muted\">Status: Rejsen er endnu ikke påbegyndt. Besvar ud fra, hvad du forventer at gøre ved forsinkelse/aflysning.</p>';\n    }\n?>\n"
text=text.replace("<?php\n    $articles", insert+"<?php\n    $articles", 1)
p.write_text(text,encoding='utf-8')
print('done')
