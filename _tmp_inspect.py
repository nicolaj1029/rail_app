from pathlib import Path
p=Path('src/Controller/FlowController.php')
text=p.read_text(encoding='utf-8',errors='replace')
start=text.find('public function assistance(')
print(text[start+7600:start+11200])
