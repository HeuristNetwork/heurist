
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
- 2025-04-12

--- Version ---
- 1.0

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
        if os.path.isdir(git_dir):  # Si un répertoire .git est trouvé
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
                    if entry.name in ("_README.md", "1-overview.txt"):
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
            # Includes typiques en PHP
            include_find = re.findall(
                r'\b(?:require|require_once|include|include_once)\s*\(?\s*(?:__DIR__|dirname\s*\(\s*__FILE__\s*\))\s*\)?\s*\.\s*[\'\"]([^\'\"]+)[\'\"]',
                content
            )
             # Traitement des scripts trouvés
            for include in include_find:
                lower_include = include.lower()
                if "external" in lower_include or "ext" in lower_include:
                    continue  # Ignore ce script
                if '/' not in include and '\\' not in include:
                    # Pas de / ou \ ➔ c'est un nom simple, on préfixe avec le répertoire du fichier actuel
                    parent_dir = Path(full_path).parent
                    corrected_path = str((parent_dir / include).as_posix())
                    includes.append(corrected_path)
                else:
                    # Chemin normal, on l'ajoute tel quel
                    includes.append(include)

            include_find = re.findall(
                r'\b(?:require|require_once|include|include_once)\s*\(?\s*[\'\"]([^\'\"]+)[\'\"]\s*\)?',
                content
            )
            for include in include_find:
                lower_include = include.lower()
                if "external" in lower_include or "ext" in lower_include:
                    continue  # Ignore ce script
                if '/' not in include and '\\' not in include:
                    # Pas de / ou \ ➔ c'est un nom simple, on préfixe avec le répertoire du fichier actuel
                    parent_dir = Path(full_path).parent
                    corrected_path = str((parent_dir / include).as_posix())
                    includes.append(corrected_path)
                else:
                    # Chemin normal, on l'ajoute tel quel
                    includes.append(include)
           
            use_statements = re.findall(r'^\s*use\s+([a-zA-Z0-9_\\]+);', content, re.MULTILINE)
            for use_stmt in use_statements:
                includes.append("/" + "/".join(use_stmt.split('\\')) + ".php")
            # Détecter les balises <script src=...> dans PHP
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
                    parent_dir = Path(full_path).parent
                    corrected_path = str((parent_dir / script_src).as_posix())
                    includes.append(corrected_path)
                else:
                    # Chemin normal, on l'ajoute tel quel
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
                #if relative_include in file_map:
                dependency_graph.add_edge(str(rel_path), relative_include)
            except ValueError:
                # Fichier en dehors du projet, on ignore
                continue

    return dependency_graph


def extract_metadata_from_file(filepath):
    metadata = {"version": None, "authors": [], "license": None}
    try:
        with open(filepath, "r", encoding="utf-8", errors="ignore") as f:
            lines = f.readlines()
        for line in lines[:50]:
            if "@version" in line:
                metadata["version"] = line.split("@version")[-1].strip()
            if "@author" in line:
                metadata["authors"].append(line.split("@author")[-1].strip())
            if "@license" in line:
                metadata["license"] = line.split("@license")[-1].strip()
    except Exception as e:
        print(f"Erreur extraction metadata pour {filepath}: {e}")
    return metadata

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

        summary[node] = {
            "depends_on": list(graph.successors(node)),
            "used_by": list(graph.predecessors(node)),
            "functions": [f for f in functions if Path(f["file"]).resolve() == full_file_path],
            "classes": [c for c in classes if Path(c["file"]).resolve() == full_file_path],
            "metadata": extract_metadata_from_file(full_file_path),
            "description": extract_description(full_file_path)
        }

    return summary


def extract_readme_content(readme_path):
    """
    Extrait le contenu d'un fichier _README.md jusqu'à la première série de tirets ('-----').
    Affiche un résumé avec 'Lire la suite...' sans ajouter de double <br>.
    """
    try:
        with open(readme_path, 'r', encoding="utf-8", errors="ignore") as f:
            lines = f.readlines()

        preview = []
        hidden = []
        part = preview  # par défaut, on remplit le preview
        max_preview_lines = 3
        line_counter = 0

        for line in lines:
            if "-----" in line:
                break

            line = line.rstrip('\n').strip()

            if not line:
                continue  # ignorer les lignes vides

            # Vérifier si c'est un titre Markdown
            if re.match(r"^#{1,6} ", line):
                level = line.count('#', 0, line.find(' '))
                title_text = html.escape(line[level+1:].strip())
                html_line = f"<h{level}>{title_text}</h{level}>"
            else:
                # Texte normal échappé
                html_line = f"{html.escape(line)}"

            if line_counter < max_preview_lines:
                preview.append(html_line)
            else:
                hidden.append(html_line)
            line_counter += 1

        if not preview and not hidden:
            return ""

        # Construction du HTML sans ajout de <br> après titres
        def join_lines(lines):
            joined = []
            for l in lines:
                joined.append(l)
                if not l.startswith('<h'):  # ajouter <br> uniquement si ce n'est pas un titre
                    joined.append('<br>')
            return ''.join(joined).rstrip('<br>')  # retirer le dernier <br> inutile

        html_preview = join_lines(preview)
        html_hidden = join_lines(hidden)

        unique_id = uuid.uuid4().hex

        if hidden:
            result = f"""
            <div class='readme-extract'>
                {html_preview}
                <a href='#' id='toggle-{unique_id}' onclick="toggleReadme('{unique_id}'); return false;">
                    <svg id='icon-{unique_id}' class='icon-svg' viewBox="0 0 24 24" width="16" height="16">
                        <path d="M8 5v14l11-7z" fill="currentColor"/>
                    </svg> 
                    
                    
                </a>
                
                <span id='hidden-{unique_id}' style='display:none;'><br>{html_hidden}</span>
                <br>
            </div>
            """
        else:
            result = f"<div class='readme-extract'>{html_preview}</div>"

        return result

    except Exception as e:
        print(f"Erreur de lecture du fichier {readme_path}: {e}")
        return ""


    
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

def export_file_summary_html_graphical(directory, summary, readme_tree, output_file="crosslink.html"):
    def render_directory(node, f, relative_root, first_call=False):
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
            render_directory(child, f, relative_root)

        f.write("</ul></li>")

    with open(output_file, "w", encoding="utf-8") as f:
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
            background-color: #f4f4f4;
            border-right: 1px solid #ccc;
            }

            #right-column {
            background-color: #ffffff;
                pre{
                    text-wrap: auto;
                }
            }
            .readme-extract{
                font-size: small;
                color: gray;
                font-style: italic;               
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
                var hidden = document.getElementById('hidden-' + id);
                var link = document.getElementById('toggle-' + id);
                var icon = document.getElementById('icon-' + id);

                if (hidden.style.display === 'none') {
                    hidden.style.display = 'inline';
                    link.childNodes[1].nodeValue = ' Replier';
                    icon.innerHTML = '<path d="M7 10l5 5 5-5H7z" fill="currentColor"/>';
                } else {
                    hidden.style.display = 'none';
                    link.childNodes[1].nodeValue = ' Lire la suite...';
                    icon.innerHTML = '<path d="M8 5v14l11-7z" fill="currentColor"/>';
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
        render_directory(readme_tree, f, directory,True)
        f.write("</ul>")
        f.write("</div>")   # Fin du left-content
        f.write("</div>")  # Fin du left-section

        f.write("<div id='right-column'>")
        f.write("<div id='right-content'>")

        # Section détails fichiers
        for file, data in summary.items():
            filename = os.path.basename(file)
            meta = data.get("metadata", {})
            anchor = filename.replace('.', '_')
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
            f.write(f"<li><strong>Description :</strong><pre>{data.get('description', '')}</pre></li>")
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

        f.write("</ul>") 
        f.write("</div>") # Fin du right-content   
        f.write("</div>") # Fin du right-section
        f.write("</div>")   # Fin du container
        f.write("<hr>")
        f.write("<button onclick='scrollToTop()' id='scrollTopBtn'>↑</button>")
        f.write("</body></html>")

    print(f"HTML exporté vers {output_file}")





# --- Programme principal ---
if __name__ == "__main__":
    # Utilisation
    directory = find_git_root()
    crossref_files = find_crossref_files(directory)
    dependency_graph = find_dependencies = find_dependencies(crossref_files)
    functions, classes = extract_php_functions_and_classes(directory)
    summary = build_file_summaries(directory,dependency_graph, functions, classes)
    report_html = os.path.join(directory, "Heurist_Functions_crossreferences_summary.html")
    export_file_summary_html_graphical(directory,summary,crossref_files,report_html)