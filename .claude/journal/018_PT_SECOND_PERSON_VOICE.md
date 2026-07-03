# 2026-07-03 — Traduções PT passam para segunda pessoa ("tu")

## O que mudou

`lang/pt.php` estava escrito em primeira pessoa ("As minhas encomendas",
"Guardei as configurações.", "Não consegui transferir o PDF."). A pedido,
convertido para tratar o utilizador na **segunda pessoa, informal ("tu")**.

Regras aplicadas:

- **Rótulos possessivos**: `minhas/meus` → `tuas/teus`
  (ex.: "As minhas encomendas" → "As tuas encomendas").
- **Instruções** → imperativo tu
  (ex.: "Introduzo…" → "Introduz…", "Indico…" → "Indica…", "Deixo…" → "Deixa…",
  "Seleciono…" → "Seleciona…", "verifico" → "verifica").
- **Estado do utilizador** → segunda pessoa
  ("Não tenho…" → "Não tens…", "Estou ligado…" → "Estás ligado…").
- **Confirmações** → "Queres …?"
  ("Descarto esta encomenda?" → "Queres descartar esta encomenda?").
- **Resultados de ações do sistema** → frase neutra/passiva, alinhada com o EN
  ("Guardei as configurações." → "Configurações guardadas.";
  "Criei o documento…" → "Documento criado…";
  "Não consegui…" → "Não foi possível…").
  Exceção onde o sujeito é claramente o utilizador:
  "Não selecionei…" → "Não selecionaste…".

Cabeçalho do ficheiro atualizado de "Perspetiva na primeira pessoa" para
"Perspetiva na segunda pessoa, tratando o utilizador por 'tu'".

## Docs

- `CLAUDE.md`: a convenção de i18n dizia "Portuguese uses first-person
  perspective" — atualizada para segunda pessoa informal + resultados
  neutros/passivos.

## Não alterado

- `lang/en.php` (já era neutro).
- Mensagens de log continuam em inglês (convenção — ver memória).
