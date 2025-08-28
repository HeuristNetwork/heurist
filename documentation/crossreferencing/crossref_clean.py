
# --- Heurist Functions: Cross-reference and Documentation Generator ---
"""     
--- Description ---
Ce script génère un rapport HTML des dépendances entre les fichiers PHP, HTML et JS d'un projet.
Il extrait également les fonctions et classes PHP, ainsi que les métadonnées (version, auteur, licence) des fichiers.
Il crée une arborescence des répertoires et un résumé de chaque fichier, incluant les dépendances, les fonctions, les classes et la description.
Le rapport est exporté au format HTML, avec une interface graphique permettant de naviguer facilement dans les fichiers et leurs dépendances.
 
--- Objectif ---
Le but de ce script est de faciliter la compréhension et la documentation des projets en PHP, HTML et JS en fournissant une vue d'ensemble des dépendances et des métadonnées.

--- Utilisation --- 
Le script est conçu pour être exécuté dans n'importe quelle branche d'un projet contenant un dépôt Git.
Il remonte à la raciine du git et recherche les fichiers PHP, HTML et JS, extrait les informations pertinentes et génère un rapport HTML.

--- Auteurs ---
- Heurist Functions Team
- [Bruno Morandière] - [bruno.morandiere@resefe.fr]

--- Date de création ---
- 2025-04-03

--- Date de mise à jour ---
- 2025-08-27

--- Version ---
- 1.1

--- Licence ---
- http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0 

# --- Remarques ---
- Ce script est conçu pour être utilisé dans un environnement Python 3.x.
- Assurez-vous d'avoir installé les bibliothèques nécessaires (networkx, html, os, re, json).
- Le script peut être exécuté depuis la ligne de commande ou intégré dans un projet plus vaste.
"""

# --- Imports ---
import os
import re
import json
import html
import networkx as nx
from pathlib import Path
import uuid
from typing import Tuple, Dict, List, Optional

# -- Regex utilitaires -------------------------------------------------------
PHP_LINE_COMMENT_RE = re.compile(r'//[^\n]*', re.MULTILINE)
PHP_BLOCK_COMMENT_RE = re.compile(r'/\*.*?\*/', re.DOTALL)
HTML_COMMENT_RE     = re.compile(r'<!--.*?-->', re.DOTALL)

# littéraux '...' ou "..." et concat . entre littéraux
_LIT    = r'([\'"])(.*?)\1'
_CONCAT = re.compile(rf'^\s*{_LIT}(?:\s*\.\s*{_LIT})*\s*$', re.DOTALL)

def _eval_php_string_concat(expr: str) -> Optional[str]:
    """
    Évalue une concat PHP composée uniquement de littéraux ('..' . "..." . '...')
    Retourne None si l'expression contient autre chose (variables, constantes inconnues, appels, etc.).
    """
    if not _CONCAT.fullmatch(expr):
        return None
    parts = [m.group(2) for m in re.finditer(_LIT, expr, re.DOTALL)]
    s = "".join(parts)
    return s.replace("\\", "/")

def _strip_comments(s: str) -> str:
    """Supprime commentaires PHP et HTML."""
    s = PHP_BLOCK_COMMENT_RE.sub('', s)
    s = PHP_LINE_COMMENT_RE.sub('', s)
    s = HTML_COMMENT_RE.sub('', s)
    return s

def _parse_php_includes(content: str, rel_path: str, debug: bool = False) -> List[str]:
    """
    Remplace __DIR__/dirname(__FILE__) par le dossier du fichier courant (relatif),
    remplace DIRECTORY_SEPARATOR / DS par '/', puis extrait les cibles d'include/require.
    """
    # 0) Nettoyage des commentaires
    content0 = _strip_comments(content)

    # 1) Unifie dirname(__FILE__) -> __DIR__  (casse insensible, 2e arg optionnel)
    #    gère: dirname(__FILE__), dirname ( __FILE__ ), dirname(__FILE__, 2)
    dirname_pat = r'dirname\s*\(\s*__FILE__\s*(?:,\s*\d+\s*)?\)'
    content1, n_dirname = re.subn(dirname_pat, '__DIR__', content0, flags=re.IGNORECASE)

    # 2) Remplace __DIR__ par un littéral de chemin relatif POSIX
    parent_dir_rel = Path(rel_path).parent.as_posix()
    content2, n_dir = re.subn(r'__DIR__', f"'{parent_dir_rel}'", content1)

    # 3) Normalise les séparateurs issus de constantes fréquentes
    content3, n_ds  = re.subn(r'\bDIRECTORY_SEPARATOR\b', "'/'", content2)
    content3, n_ds2 = re.subn(r'\bDS\b', "'/'", content3)  # si un alias DS est utilisé

    if debug:
        print(f"[parse_includes] replacements: dirname={n_dirname}, __DIR__={n_dir}, "
              f"DIR_SEP={n_ds}, DS={n_ds2}")

    # 4) Capture de l'expression passée à include/require (jusqu'au ;)
    #    (?s) pour autoriser retours à la ligne
    stmt_re = re.compile(
    r'^\s*(?:require|include)(?:_once)?\s*\(?\s*(?P<expr>[^;]+?)\s*\)?\s*;',
    re.IGNORECASE | re.MULTILINE | re.DOTALL
)

    includes: List[str] = []
    for m in stmt_re.finditer(content3):
        expr = m.group('expr').strip()
        # Évalue uniquement concat de littéraux ('..' . "...")
        path = _eval_php_string_concat(expr)
        if path is None:
            if debug:
                # aide au débogage : montre l'expression non résolue
                print(f"[parse_includes] non résolu: {expr!r}")
            continue
        path = path.strip().replace("\\", "/")
        if path:
            includes.append(path)

    if debug and not includes:
        print("[parse_includes] aucun include résolu (après normalisation)")

    return includes




# --- Fonctions ---
def truncate_description(description, length=100):
    if len(description) > length:
        return description[:length] + "..."
    return description

def find_git_root():
    """
    Remonte dans les répertoires parents jusqu'à ce qu'un répertoire .git soit trouvé.
    Retourne le chemin absolu du répertoire contenant .git.
    """
    current_dir = os.getcwd()  # Commence à partir du répertoire courant
    
    while current_dir != os.path.dirname(current_dir):  # Tant qu'on n'est pas à la racine
        git_dir = os.path.join(current_dir, ".git")
        htacccess_file = os.path.join(current_dir, ".htaccess")
        if htacccess_file and os.path.isfile(htacccess_file):
            return current_dir
        if os.path.isdir(git_dir) :  # Si un répertoire .git est trouvé
            return current_dir
        current_dir = os.path.dirname(current_dir)  # Remonter d'un niveau
    
    return None  # Si .git n'est pas trouvé, retourner None



def find_crossref_files(directory):
    """
    Recherche tous les répertoires et construit une arborescence complète :
    - fichiers README (_README.md ou 1-overview.txt)
    - fichiers .php, .html, .js
    """
    def scan_dir(path):
        node = {
            "path": path,
            "readmes": [],
            "code_files": [],
            "children": {}
        }
        try:
            for entry in os.scandir(path):
                if entry.is_dir(follow_symlinks=False):
                    if entry.name != ".git":
                        node["children"][entry.name] = scan_dir(entry.path)
                elif entry.is_file(follow_symlinks=False):
                    if entry.name in ("_README.md","README.md", "1-overview.txt"):
                        node["readmes"].append(entry.name)
                    elif entry.name.lower().endswith((".php", ".html", ".js")): #,".json"
                        node["code_files"].append(entry.name)
        except PermissionError:
            # Ignore inaccessible directories
            pass
        return node

    return scan_dir(directory)



def extract_all_files(crossref_tree):
    """
    À partir du résultat de find_crossref_files, extrait tous les fichiers avec leur chemin.
    """
    files = []

    def recurse(node):
        for code_file in node.get("code_files", []):
            files.append(Path(node["path"]) / code_file)
        for child in node.get("children", {}).values():
            recurse(child)

    recurse(crossref_tree)
    return files

def find_dependencies(crossref_tree):
    """
    Construit un graphe de dépendances à partir des fichiers issus de find_crossref_files.
    """
    dependency_graph = nx.DiGraph()
    all_files = extract_all_files(crossref_tree)
    base_dir = Path(crossref_tree["path"])

    file_map = {str(f.relative_to(base_dir)): f for f in all_files}

    for rel_path, full_path in file_map.items():
        try:
            content = full_path.read_text(encoding="utf-8", errors="ignore")
        except Exception:
            continue  # Ignore les erreurs de lecture

        includes = []

        if rel_path.endswith(".php"):
           # 1) Includes / require (après normalisation __DIR__/dirname(__FILE__))
            include_list = _parse_php_includes(content, rel_path)  

            # 2) Filtrage et correction des chemins simples
            includes = []
            for inc in include_list:
                lower_inc = inc.lower()
                if "external" in lower_inc or "/ext/" in lower_inc or lower_inc.endswith("/ext"):
                    continue
                if '/' not in inc and '\\' not in inc:
                    parent_dir = Path(rel_path).parent
                    corrected_path = str((parent_dir / inc).as_posix())
                    includes.append(corrected_path)
                else:
                    includes.append(inc)
            # 3) use ... (premier segment en minuscule uniquement)
            use_statements = re.findall(r'(?m)^\s*use\s+([a-z][A-Za-z0-9_]*(?:\\[A-Za-z_][A-Za-z0-9_]*)*)\s*;', content)
            for use_stmt in use_statements:
                includes.append("/" + "/".join(use_stmt.split('\\')) + ".php")

            # 4) <script src="...js"> — ignorer ce qui est dans <!-- ... -->
            '''
            script_includes = re.findall(
                r'<script[^>]+src=["\'](?:[^"\']*?)((?:[^"\'>]+\.js))["\']', content, flags=re.IGNORECASE
            )
            # Traitement des scripts trouvés
            for script_src in script_includes:
                lower_src = script_src.lower()
                if "external" in lower_src or "ext" in lower_src:
                    continue  # Ignore ce script
                if '/' not in script_src and '\\' not in script_src:
                    # Pas de / ou \ ➔ c'est un nom simple, on préfixe avec le répertoire du fichier actuel
                    #parent_dir = Path(full_path).parent
                    parent_dir = Path(rel_path).parent
                    corrected_path = str((parent_dir / script_src).as_posix())
                    includes.append(corrected_path)
                    #includes.append(script_src)

                else:
                    # Chemin normal, on l'ajoute tel quel
                    includes.append(script_src)
            '''
            content_no_html = HTML_COMMENT_RE.sub('', content)
           
            script_includes = re.findall(
                r'<script[^>]+src=["\'](?:[^"\']*?)((?:[^"\'>]+\.js))["\']', content_no_html, flags=re.IGNORECASE
            )
            for script_src in script_includes:
                lower_src = script_src.lower()
                if "external" in lower_src or "/ext/" in lower_src or lower_src.endswith("/ext"):
                    continue
                
                includes.append(script_src)

        elif rel_path.endswith(".html"):
            # Liens vers scripts ou href
            include = re.findall(r'(?:href|src)="(.*?\\.php|.*?\\.js)"', content)
            includes += include

        elif rel_path.endswith(".js"):
            # Imports JS classiques
            imports = re.findall(r'import\\s+.*?from\\s+[\'"](.*?\\.js)[\'"]', content)
            requires = re.findall(r'require\\(\\s*[\'"](.*?\\.js)[\'"]\\s*\\)', content)
            includes += imports + requires
        # elif rel_path.endswith(".json"):
        #     try:
        #         json_data = json.loads(content)
        #         if isinstance(json_data, list):
        #             for item in json_data:
        #                 if isinstance(item, dict):
        #                     href = item.get("href")
        #                     if href and isinstance(href, str):
        #                         includes.append(href)
        #     except json.JSONDecodeError as e:
        #      print(f"Erreur de parsing JSON pour {rel_path}: {e}")

        # Ajouter les noeuds
        dependency_graph.add_node(str(rel_path))
        
        for include in includes:
            if include.startswith("http"):
                # Ignore les liens externes
                continue
            include = include.lstrip("/")  # on travaille en relatif
            #include_path = (Path(rel_path).parent / include).resolve()
            include_path = (Path(base_dir) / include).resolve()

            try:
                relative_include = str(include_path.relative_to(base_dir.resolve()))
            except ValueError:
                # Fichier en dehors du projet, on ignore sans bruit
                continue

            # Normalisation pour comparer proprement avec les clés de file_map
            rel_norm = Path(relative_include).as_posix()

            if include_path.exists():
                dependency_graph.add_edge(str(rel_path), rel_norm)
                #print(f"Ajout de l'arête : {rel_path} -> {rel_norm}")

            else:
                parent_dir = Path(rel_path).parent
                corrected_path = Path(parent_dir / include).as_posix()
                include_path = Path(base_dir / corrected_path).resolve()
                if Path(include_path).exists():
                    dependency_graph.add_edge(str(rel_path), corrected_path)
                    #print(f"Ajout de l'arête : {rel_path} -> {corrected_path}")

                else:
                    # les erreurs restantes sont ignorées silencieusement car elles sont  hors projet
                    pass
                    #print(f"Dans le fichier {rel_path}, appel de {corrected_path} qui n'existe pas")

        
    return dependency_graph


# --- Regex utilitaires ---

DOCBLOCK_RE  = re.compile(r'/\*\*(.*?)\*/', re.DOTALL)            # intérieur d'un /** ... */
LEADING_STAR = re.compile(r'^\s*\*\s?')                           # marge " * "
TAG_START    = re.compile(r'^@([A-Za-z][A-Za-z0-9_-]*)\b(.*)$')   # @tag + payload
SEP_AUTHORS  = re.compile(r'\s*(?:,|;|\||/| and )\s*', re.IGNORECASE)
HTML_TAG_RE  = re.compile(r'</?([A-Za-z][A-Za-z0-9:-]*)(?:\s[^>]*)?>')


def _clean(s: Optional[str]) -> Optional[str]:
    if s is None:
        return None
    s = str(s).strip()
    return s if s else None

def _strip_html_tags(text: str) -> str:
    if not text: return ""
    no_tags = HTML_TAG_RE.sub('', text)
    no_tags = html.unescape(no_tags)
    lines = [ln.rstrip() for ln in no_tags.splitlines()]
    return "\n".join(lines).strip()


def _extract_title_from_docblock(block: str, filename: str) -> Optional[str]:
    """Prend les premières lignes avant tout @tag. Si 'filename - Titre' => renvoie Titre."""
    lines = [LEADING_STAR.sub("", l).strip() for l in block.splitlines()]
    pre_tag: List[str] = []
    for ln in lines:
        if ln.startswith("@"):  # début des tags -> stop
            break
        pre_tag.append(ln)
    # nettoie lignes vides en tête
    while pre_tag and not pre_tag[0]:
        pre_tag.pop(0)
    if not pre_tag:
        return None
    first = _strip_html_tags(pre_tag[0])

    # schémas fréquents : "configIni.php - Titre", "configIni.php — Titre", "configIni.php: Titre"
    base = filename
    m = re.match(rf'`?{re.escape(base)}`?\s*[-—:]\s*(.+)$', first, flags=re.IGNORECASE)
    if m:
        title = m.group(1).strip()
    else:
        # si la partie gauche ressemble à un nom de fichier, prends la droite
        m2 = re.match(r'(.+?\.\w{2,8})\s*[-—:]\s*(.+)$', first)
        title = (m2.group(2) if m2 else first).strip()

    return title.rstrip(" .") or None

def _commit_tag(metadata: Dict, tag: str, buffer: List[str]) -> None:
    """Applique le contenu agrégé au champ correspondant et vide le buffer."""
    text = _clean("\n".join(buffer).strip())
    if not text:
        return
    t = tag.lower()

    if t == "version":
        # Garde la première si plusieurs
        if not metadata["version"]:
            metadata["version"] = text

    elif t == "author":
        # Plusieurs auteurs possibles, sur une ou plusieurs lignes
        for line in text.splitlines():
            line = line.strip()
            if not line:
                continue
            parts = [p.strip() for p in SEP_AUTHORS.split(line) if p.strip()]
            metadata["authors"].extend(parts)

    elif t in ("license", "licence"):
        if not metadata["license"]:
            metadata["license"] = text

    elif t == "fileoverview":
        # Mappe vers "description", multi-lignes
        if metadata["description"]:
            metadata["description"] += "\n" + text
        else:
            metadata["description"] = text
    elif t == "brief":
        # Mappe vers "brief", multi-lignes 
        if metadata["brief"]:
            metadata["brief"] += "\n" + text
        else:
            metadata["brief"] = text

def extract_metadata_from_file(filepath: str, max_lines: int = 200) -> Tuple[Dict, Dict]:
    """
    Extrait metadata depuis les docblocks d'en-tête (/** ... */) dans les max_lines premières lignes :
      - version : str|None
      - authors : List[str]
      - license : str|None
      - description : str|None (depuis @fileOverview)
    Retourne (metadata, missing_flags).
    """
    metadata: Dict[str, object] = {"title":None, "version": None, "authors": [], "license": None, "description": None, "brief": None}

    # Lecture limitée à l'en-tête
    try:
        head_lines: List[str] = []
        with open(filepath, "r", encoding="utf-8", errors="ignore") as f:
            for i, line in enumerate(f):
                if i >= max_lines:
                    break
                head_lines.append(line.rstrip("\n"))
        head = "\n".join(head_lines)
    except Exception as e:
        print(f"Erreur extraction metadata pour {filepath}: {e}")
        missing = {"version": True, "authors": True, "license": True, "description": True}
        return metadata, missing

    # Trouve tous les docblocks dans la tête de fichier
    blocks = DOCBLOCK_RE.findall(head)
    filename = Path(filepath).name

    # Titre : on tente sur le premier docblock qui a un préambule
    for block in blocks:
        title = _extract_title_from_docblock(block, filename)
        if title:
            metadata["title"] = title
            break

    # Parse chaque docblock (dans l'ordre)
    for block in blocks:
        current_tag: Optional[str] = None
        buffer: List[str] = []

        for raw in block.splitlines():
            line = LEADING_STAR.sub("", raw).strip()  # enlève la marge "* "

            m = TAG_START.match(line)
            if m:
                # Commit du tag précédent
                if current_tag is not None:
                    _commit_tag(metadata, current_tag, buffer)
                    buffer = []

                current_tag = m.group(1)            # nom du tag (sans le @)
                first_payload = m.group(2).strip()  # texte sur la même ligne
                if first_payload:
                    buffer.append(first_payload)
                continue

            # À l'intérieur d'un tag en cours, on accumule jusqu'au prochain @xxx
            if current_tag is not None:
                buffer.append(line)

        # Commit final du bloc
        if current_tag is not None:
            _commit_tag(metadata, current_tag, buffer)

    # Nettoyage auteurs (dédup)
    authors_clean, seen = [], set()
    for a in metadata["authors"]:
        a = _clean(a)
        if a and a not in seen:
            authors_clean.append(a)
            seen.add(a)
    metadata["authors"] = authors_clean

    # Flags "manquants" (ignore .html/.htm)
    suffix = Path(filepath).suffix.lower()
    check = suffix not in {".html", ".htm"}

    missing_flags: Dict[str, object] = {
        "version":      check and not _clean(metadata["version"]),
        "authors":      check and not bool(metadata["authors"]),
        "license":      check and not _clean(metadata["license"]),
        "description":  check and not _clean(metadata["description"]),
        "brief":        check and not _clean(metadata["brief"]),
    }
    return metadata, missing_flags



def extract_php_functions_and_classes(directory):
    functions = []
    classes = []
    directory = os.path.abspath(directory)
    for root, _, files in os.walk(directory):
        for file in files:
            if file.endswith(".php"):
                path = os.path.join("./",os.path.relpath(os.path.join(root, file)))
                try:
                    with open(path, "r", encoding="utf-8", errors="ignore") as f:
                        lines = f.readlines()
                    for i, line in enumerate(lines):
                        func_match = re.match(r"\s*function\s+([a-zA-Z_][a-zA-Z0-9_]*)", line)
                        if func_match:
                            functions.append({"function": func_match.group(1), "file": path, "line": i + 1})
                        class_match = re.match(r"\s*class\s+([a-zA-Z_][a-zA-Z0-9_]*)", line)
                        if class_match:
                            classes.append({"class": class_match.group(1), "file": path, "line": i + 1})
                except Exception as e:
                    print(f"Erreur avec {path}: {e}")
    return functions, classes


def build_file_summaries(directory, graph, functions, classes):
    """
    Construit un résumé pour chaque fichier du graphe : dépendances, fonctions, classes, métadonnées, description.
    
    Args:
        directory (str or Path): Dossier racine du projet.
        graph (nx.DiGraph): Graphe des dépendances.
        functions (list): Liste des fonctions extraites.
        classes (list): Liste des classes extraites.

    Returns:
        dict: Résumé par fichier.
    """
    summary = {}
    base_dir = Path(directory).resolve()

    for node in graph.nodes:
        full_file_path = (base_dir / node).resolve()
        metadata,missing_flags = extract_metadata_from_file(full_file_path)
        summary[node] = {
            "depends_on": list(graph.successors(node)),
            "used_by": list(graph.predecessors(node)),
            "functions": [f for f in functions if Path(f["file"]).resolve() == full_file_path],
            "classes": [c for c in classes if Path(c["file"]).resolve() == full_file_path],
            "metadata": metadata,   
            "missing_metadata": missing_flags,
            "description": extract_description(full_file_path)
        }

    return summary


def extract_readme_content(readme_path):
    """
    Rend un extrait HTML :
      - aperçu = section 'Overview' si présente (sinon fallback: 1er bloc)
      - 'Lire la suite...' pour afficher le README complet
    """
    try:
        with open(readme_path, 'r', encoding="utf-8", errors="ignore") as f:
            lines = [ln.rstrip("\n") for ln in f]
    except Exception as e:
        print(f"Erreur de lecture du fichier {readme_path}: {e}")
        return ""

    if not lines:
        return ""

    # --------- Helpers: rendu Markdown minimal (titres, puces, paragraphes, code inline) ----------
    header_re = re.compile(r'^\s{0,3}(#{1,6})\s+(.*)$')
    list_re   = re.compile(r'^\s*[-*]\s+(.*)$')

    def _inline(s: str) -> str:
        # échappe puis rend le code inline `...` en <code>...</code>
        s = html.escape(s, quote=False)
        return re.sub(r'`([^`]+)`', lambda m: f"<code>{m.group(1)}</code>", s)

    def render_md_minimal(md_lines):
        out = []
        i, n = 0, len(md_lines)
        while i < n:
            raw = md_lines[i]
            # Ligne vide -> saute / sépare
            if not raw.strip():
                i += 1
                continue

            # Titres
            m = header_re.match(raw)
            if m:
                level = len(m.group(1))
                text  = _inline(m.group(2).strip())
                out.append(f"<h{level}>{text}</h{level}>")
                i += 1
                continue

            # Listes à puces
            m = list_re.match(raw)
            if m:
                items = []
                while i < n:
                    m2 = list_re.match(md_lines[i])
                    if not m2:
                        break
                    items.append(f"<li>{_inline(m2.group(1).strip())}</li>")
                    i += 1
                out.append("<ul>" + "".join(items) + "</ul>")
                continue

            # Paragraphe (jusqu'à ligne vide / titre / puce)
            para = [raw]
            i += 1
            while i < n and md_lines[i].strip() and not header_re.match(md_lines[i]) and not list_re.match(md_lines[i]):
                para.append(md_lines[i])
                i += 1
            # Join avec espace pour éviter des <br> partout
            out.append(f"<p>{_inline(' '.join(para).strip())}</p>")

        return "".join(out)

    # --------- Extraction de la section Overview ----------
    overview_start = None
    overview_level = None
    for idx, ln in enumerate(lines):
        m = header_re.match(ln)
        if m:
            title = m.group(2).strip()
            if title.lower().startswith("overview"):
                overview_start = idx
                overview_level = len(m.group(1))
                break

    if overview_start is not None:
        # bornes: de la ligne suivante jusqu’au prochain titre de niveau <= overview_level
        start = overview_start + 1
        end = len(lines)
        for j in range(start, len(lines)):
            m2 = header_re.match(lines[j])
            if m2 and len(m2.group(1)) <= overview_level:
                end = j
                break
        # Construire l’aperçu = titre "Overview" + contenu de la section
        overview_block = [lines[overview_start]] + lines[start:end]
        html_preview = render_md_minimal(overview_block)
    else:
        # Fallback: premier bloc non vide (jusqu’à ligne vide)
        k = 0
        while k < len(lines) and not lines[k].strip():
            k += 1
        block = []
        while k < len(lines) and lines[k].strip():
            block.append(lines[k]); k += 1
        html_preview = render_md_minimal(block if block else lines[:3])

    # --------- Rendu complet ----------
    html_full = render_md_minimal(lines)

    # --------- Assemblage avec toggle ---------

    uid = uuid.uuid4().hex
    if html_full and html_full != html_preview:
        # Lien 'Lire la suite...' + icône, compatible avec votre toggleReadme()
        return f"""
        <div class='readme-container'>
            
            <div class='readme-extract'>
                <div id="preview-{uid}" class='readme-preview'>
                    {html_preview}           
                </div>
                <div id="full-{uid}" class='readme-full' style="display:none;">
                    {html_full}
                </div>
                <br>
            </div>
            <button id="toggle-{uid}" onclick="toggleReadme('{uid}'); return false;">
                <svg xmlns="http://www.w3.org/2000/svg" clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" width="20" height="20" viewBox="0 0 24 24"><path d="m11 11h-7.25c-.414 0-.75.336-.75.75s.336.75.75.75h7.25v7.25c0 .414.336.75.75.75s.75-.336.75-.75v-7.25h7.25c.414 0 .75-.336.75-.75s-.336-.75-.75-.75h-7.25v-7.25c0-.414-.336-.75-.75-.75s-.75.336-.75.75z" fill-rule="nonzero"></path></svg>
            </button>
           
        </div>
        """
    else:
        return f"<div class='readme-extract'>{html_preview}</div>"

    
def extract_description(filepath):
    try:
        with open(filepath, "r", encoding="utf-8", errors="ignore") as f:
            lines = f.readlines()
        comments = []
        for line in lines[:50]:
            if re.match(r"\s*(\/\/|\*|#)", line):
                clean = re.sub(r"^\s*(\/\/|\*|#)\s?", "", line).strip()
                if clean and not clean.startswith("@"):
                    comments.append(clean)
        if comments:
            return "\n".join(comments[:5])
    except Exception as e:
        print(f"Erreur extraction description pour {filepath}: {e}")
    return ""

def _safe_pre_html(value) -> str:
    """Évite de casser la page : normalise puis échappe <, >, & (sans doubler les entités)."""
    if value is None:
        return ""
    if isinstance(value, (list, tuple)):
        value = "\n".join(map(str, value))
    s = html.unescape(str(value))   # évite le double-escape si déjà encodé (&lt; etc.)
    s = s.replace("\x00", "")       # nettoie les NUL chars éventuels
    return html.escape(s, quote=False)  # garde les guillemets tels quels (inutile dans <pre>)

def export_file_summary_html_graphical(directory, summary, readme_tree, output_file="crosslink.html"):
    def render_directory(node, f, relative_root, first_call=False):
        missing_readmes = []

        relative_path = os.path.relpath(node['path'], relative_root)
        safe_id = relative_path.replace(os.sep, '_').replace(' ', '_')

        f.write(f"<li><span class='folder' onclick='toggleVisibility(\"{safe_id}\")'>📁 {os.path.basename(relative_path)}</span>")
        if first_call:
            f.write(f"<ul id='{safe_id}' style='display:block;'>")
        else:
            f.write(f"<ul id='{safe_id}' style='display:none;'>")

        # --- README Files (en haut) ---
        if node['readmes']:
            for readme_file in sorted(node['readmes']):
                readme_path = os.path.join(node['path'], readme_file)
                try:
                    readme_content = extract_readme_content(readme_path)
                    f.write(f"<li>{readme_content}</li>")
                except Exception as e:
                    print(f"Erreur de lecture du fichier {readme_path}: {e}")
                    f.write("<li><p class='warning'>Erreur de lecture</p></li>")
        else:
            f.write("<li><p class='warning'>No readme</p></li>")
            missing_readmes.append(relative_path)

        # --- Séparer les fichiers par type ---
        php_files = []
        html_files = []
        js_files = []
        other_files = []

        for code_file in node['code_files']:
            ext = os.path.splitext(code_file)[1].lower()
            if ext == '.php':
                php_files.append(code_file)
            elif ext == '.html':
                html_files.append(code_file)
            elif ext == '.js':
                js_files.append(code_file)
            else:
                other_files.append(code_file)

        # --- Fonction d'affichage ---
        def write_code_files(file_list, icon):
            for code_file in sorted(file_list):
                filepath = os.path.abspath(os.path.join(node['path'], code_file))
                description = ""
                description = extract_description(filepath)
                name = os.path.basename(code_file)
                anchor = name.replace('.', '_')
                f.write(f"<li>{icon} <a href='#{anchor}' title='{description}'>{name}</a> - <span class='description'>{truncate_description(description)}</span></li>")

        # --- Maintenant afficher dans l'ordre souhaité ---
        write_code_files(php_files, '🐘')
        write_code_files(html_files, '🌐')
        write_code_files(js_files, '🧩')
        write_code_files(other_files, '📄')
        
        # --- Children (sous-dossiers) ---
        for child in sorted(node['children'].values(), key=lambda c: os.path.basename(c['path']).lower()):
            missing_readmes.extend(render_directory(child, f, relative_root))

        f.write("</ul></li>")
        # <- on retourne la liste pour cet arbre
        return missing_readmes

    with open(output_file, "w", encoding="utf-8") as f:
        f.write("""
                <!--
                /**
                * Heurist_Function_crossreference_summary.html - Heurist_Functions_crossreferences_summary.html provides a comprehensive overview of a project’s PHP, JS, and HTML sources and the relationships between them.
                *
                * @fileOverview Heurist_Functions_crossreferences_summary.html provides a comprehensive overview of a project’s PHP, JS, and HTML sources and the relationships between them.
                * It maps dependencies (PHP include/require, JS imports, <script src>), extracts PHP functions and classes, and collects file metadata (version, authors, license, description).
                * @project     Heurist academic knowledge management system
                * @link https://HeuristNetwork.org
                * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
                * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
                * @author bruno.morandiere@resefe.fr
                * 
                */
                -->
                """)
        f.write("<html><head><meta charset='UTF-8'><title>Heurist Functions: Dependencies and Summary</title>")
        f.write("""
        
        <style>
            /* Layout principal */
            body {
                margin: 0;
                font-family: sans-serif;
                background-color: var(--bg);
                color: var(--text);
            }

            :root {
                --bg: #ffffff;
                --text: #000000;
            }
            @media (prefers-color-scheme: dark) {
                :root {
                    --bg: #121212;
                    --text: #eeeeee;
                }
            }
                
            ul{
                list-style-type:none;
                }

            .description{
                font-size: 12px;
                color: gray;
                font-style: italic;
            }
                .warning{
                font-size: 12px;
                color: orange;
            }
            /* Header fixe en haut */
            header {
            background-color: #336699;
            color: white;
            text-align: center;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 100;
            height: 80px; /* Espace pour le header */               
            }

            /* Conteneur global sous le header */
            #container {
            display: flex;
            height: calc(100vh - 80px); /* Hauteur totale - hauteur du header */
            top: 80px; /* Espace pour le header */ 
            position: fixed;
            }

            /* Colonnes */
            #left-column, #right-column {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            }
                

            /* Optionnel : bordure visuelle */
            #left-column {
            background-color: #ffffff;
            border-right: 1px solid #ccc;
            }

            #right-column {
            background-color: #ffffff;
                pre{
                    text-wrap: auto;
                }
            }
            .readme-container{
                display: flex;
                button {
                    border-radius: 5px;
                    background-color: rgba(255, 255, 255, 0.8);
                    border: 1px solid #9e9e9e;
                    justify-content: center;
                    align-items: center;
                    height: 30px;
                    width: 30px;
                    margin-left: 6px;
                    display: flex;
                }
                .readme-extract{
                    font-size: small;
                    color: gray;
                    font-style: italic;  
                    border: 1px solid #ccc;
                    border-radius: 5px;
                    padding: 5px;
                    background-color: rgb(252, 252, 252);
                    H1, H2, H3, H4, H5, H6 {
                    font-size: 12px;
                    }           
                }
            }
            #top-buttons button {
                margin-left: 5px;
            }

            
            /* Bouton Scroll Top */
            #scrollTopBtn {
                position: fixed;
                bottom: 30px;
                right: 30px;
                display: none;
                background-color: #336699;
                color: white;
                border: none;
                padding: 10px;
                border-radius: 50%;
                font-size: 18px;
                cursor: pointer;
                z-index: 1000;
            }

            /* Responsive pour petits écrans */
            @media (max-width: 768px) {
                #container {
                    flex-direction: column;
                }
                #sidebar {
                    position: absolute;
                    left: 0;
                    top: 70px;
                    width: 250px;
                    height: calc(100vh - 70px);
                    background: var(--bg);
                    z-index: 500;
                }
                #content {
                    padding: 1em;
                }
            }
        </style>

        <script>
            function toggleVisibility(id) {
                var element = document.getElementById(id);
                if (element) {
                    element.style.display = (element.style.display === 'none') ? 'block' : 'none';
                }
            }

            function expandAll() {
                var lists = document.querySelectorAll('ul');
                lists.forEach(function(ul) {
                    ul.style.display = 'block';
                });
            }

            function collapseAll() {
                var lists = document.querySelectorAll('ul');
                lists.forEach(function(ul) {
                    ul.style.display = 'none';
                });
            }

            function toggleSidebar() {
                document.getElementById('sidebar').classList.toggle('hidden');
            }

            function scrollToTop() {
                window.scrollTo({top: 0, behavior: 'smooth'});
            }

            window.onscroll = function() {
                var btn = document.getElementById("scrollTopBtn");
                if (document.body.scrollTop > 300 || document.documentElement.scrollTop > 300) {
                    btn.style.display = "block";
                } else {
                    btn.style.display = "none";
                }
            }

            function toggleReadme(id) {
                var preview = document.getElementById('preview-' + id);
                var full = document.getElementById('full-' + id);
                var button = document.getElementById('toggle-' + id);

                if (preview.style.display === 'none') {
                    preview.style.display = 'inline';
                    full.style.display = 'none';
                    button.innerHTML ='<svg xmlns="http://www.w3.org/2000/svg" clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" width="20" height="20" viewBox="0 0 24 24"><path d="m11 11h-7.25c-.414 0-.75.336-.75.75s.336.75.75.75h7.25v7.25c0 .414.336.75.75.75s.75-.336.75-.75v-7.25h7.25c.414 0 .75-.336.75-.75s-.336-.75-.75-.75h-7.25v-7.25c0-.414-.336-.75-.75-.75s-.75.336-.75.75z" fill-rule="nonzero"></path></svg>';

                
                } else {
                    preview.style.display = 'none';
                    full.style.display = 'block';
                    button.innerHTML ='<svg xmlns="http://www.w3.org/2000/svg" clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" width="20" height="20" viewBox="0 0 24 24"><path d="m21 11.75c0-.414-.336-.75-.75-.75h-16.5c-.414 0-.75.336-.75.75s.336.75.75.75h16.5c.414 0 .75-.336.75-.75z" fill-rule="nonzero"></path></svg>';

                }
            }
            </script>

        """)
        f.write("</head><body>")

        f.write("<header>")
        f.write("<h1>Heurist Functions: Dependencies and Summary</h1>")
        f.write("</header>")
        f.write("<div id='container'>")

        f.write("<div id='left-column'>")
        f.write("<div id='left-content'>")
        f.write("<ul>")
        missing_readmes = render_directory(readme_tree, f, directory,True)
        f.write("</ul>")
        f.write("</div>")   # Fin du left-content
        f.write("</div>")  # Fin du left-section

        f.write("<div id='right-column'>")
        f.write("<div id='right-content'>")

        # Section détails fichiers
        for file, data in summary.items():
            try:
                filename = os.path.basename(file)
                meta = data.get("metadata", {})
                anchor = filename.replace('.', '_')
                f.write("<div class='file-summary'>")
                f.write(f"<h2 id='{anchor}'>{filename}</h2>")
                f.write("<ul>")

                full_file_path = os.path.join(directory, file)

                f.write(f"<li><strong>Chemin :</strong> <a href='{full_file_path}' target='_blank'>{file}</a></li>")
                f.write(f"<li><strong>Version :</strong> {meta.get('version') or 'non spécifiée'}</li>")
                if meta.get("authors"):
                    f.write("<li><strong>Auteurs :</strong><ul>")
                    for a in meta["authors"]:
                        f.write(f"<li>{a}</li>")
                    f.write("</ul></li>")
                f.write(f"<li><strong>Licence :</strong> {meta.get('license') or 'non spécifiée'}</li>")

                desc_meta = _safe_pre_html(meta.get("description"))
                desc_data = _safe_pre_html(data.get("description", ""))
                title = meta.get("title")
                brief = _safe_pre_html(meta.get("brief"))
                if desc_meta.strip():
                    f.write(f"<li><strong>Description :</strong> ")
                    if title:
                        f.write(f"{html.escape(title)}<br>")
                    if brief:
                        f.write(f"<em>{brief}</em><br>")

                    f.write(f"{desc_meta}</li>")
                    
                else:
                    # description extraite directement du fichier et non de @fileOverview
                    f.write(f"<li><strong>Description :</strong> ")

                    if title:
                        f.write(f"{html.escape(title)}<br>")
                    if brief:
                        f.write(f"<em>{brief}</em><br>")

                    f.write(f"{desc_data}</li>")
                f.write("</ul>")

                f.write("<h3>Dépend de</h3><ul>")
                for dep in data["depends_on"]:
                    link = os.path.basename(dep).replace('.', '_')
                    f.write(f"<li><a href='#{link}'>{dep}</a></li>")
                if not data["depends_on"]:
                    f.write("<li>Aucune</li>")
                f.write("</ul>")


                f.write("<h3>Utilisé par</h3><ul>")
                for user in data["used_by"]:
                    link = os.path.basename(user).replace('.', '_')
                    label = f"{user} (HTML)" if user.endswith(".html") else user
                    f.write(f"<li><a href='#{link}'>{label}</a></li>")
                if not data["used_by"]:
                    f.write("<li>Aucun</li>")
                f.write("</ul>")

                f.write("<h3>Fonctions</h3><ul>")
                for func in data["functions"]:
                    f.write(f"<li>{func['function']} (ligne {func['line']})</li>")
                if not data["functions"]:
                    f.write("<li>Aucune</li>")
                f.write("</ul>")

                f.write("<h3>Classes</h3><ul>")
                for cls in data["classes"]:
                    f.write(f"<li>{cls['class']} (ligne {cls['line']})</li>")
                if not data["classes"]:
                    f.write("<li>Aucune</li>")
                f.write("</ul><hr>")
                f.write("</div>") # Fin du file-summary
            except Exception as e:
                print(f"Erreur lors de l'écriture des détails pour {file}: {e}")
        f.write("</ul>") 
        f.write("</div>") # Fin du right-content   
        f.write("</div>") # Fin du right-section
        f.write("</div>")   # Fin du container
        f.write("<hr>")
        f.write("<button onclick='scrollToTop()' id='scrollTopBtn'>↑</button>")
        f.write("</body></html>")

    print(f"HTML exporté vers {output_file}")
    return missing_readmes



def export_dependency_json(graph, output_file="dependencies_tree.json"):
    tree = {}
    for node in graph.nodes:
        children = list(graph.successors(node))
        tree[node] = children
    with open(output_file, "w", encoding="utf-8") as f:
        json.dump(tree, f, indent=2)
    print(f"Fichier JSON exporté vers {output_file}")

# --- Programme principal ---
if __name__ == "__main__":
    # Utilisation
    directory = find_git_root()

    if directory:
        crossref_files = find_crossref_files(directory)
        dependency_graph = find_dependencies(crossref_files)
        
        #export_dependency_json(dependency_graph, os.path.join(directory, "Heurist_Functions_crossreferences.json")) # Export JSON  

        functions, classes = extract_php_functions_and_classes(directory)
        summary = build_file_summaries(directory,dependency_graph, functions, classes)
        report_html = os.path.join(directory, "Heurist_Functions_crossreferences_summary.html")
        dirs_without_readme = export_file_summary_html_graphical(directory,summary,crossref_files,report_html)

        print("\nRépertoires sans README :")

        for d in dirs_without_readme:
            print(f"- {d}")

        missing_metadata_files = [f for f, data in summary.items() if any(data["missing_metadata"].values())]
        if missing_metadata_files:
            print("\nFichiers avec métadonnées manquantes :")
            for f in missing_metadata_files:
                flags = summary[f]["missing_metadata"]
                missing_parts = [k for k, v in flags.items() if v]
                print(f"- {f} (manque: {', '.join(missing_parts)})")
        else:
            print("\nToutes les métadonnées sont présentes dans les fichiers analysés.")
        print(f"\nAnalyse terminée pour le projet dans {directory}")
    else:
        print("Aucun dépôt Git trouvé dans le répertoire courant ou ses parents.")