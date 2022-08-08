/**
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Gonçalo Cordeiro   <gzcordeiro@gmail.com>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

regional['pt'] = {
    language: 'Português',
    Databases: 'Bases de dados',
    
    //common words
    'Close': 'Fechar',
    'Settings': 'Configuração',
    'Help': 'Ajuda',
    'Error': 'Erro',
    'records': 'registos',
    'loading': 'a carregar',
    'preparing': 'a preparar',
    'explanations': 'descrições',
    'Show data': 'Mostrar os dados',
    'Actions': 'Ações',
    'Embed': 'Incorporar',
    'Clear': 'Limpar',
    'Warning': 'Aviso',
    'Cancel': 'Cancelar',
    'Record Info': 'Informações do registo',
    'Add': 'Adicionar',
    'Assign': 'Atribuir',
    'Apply': 'Aplicar',
    'Abort': 'Interromper',
    'Define': 'Definir',
    'Edit': 'Editar',
    'Delete': 'Eliminar',
    'Create': 'Criar',
    'Confirm': 'Confirmar',
    'Proceed': 'Prosseguir',
    'Continue': 'Continuar',
    'Go': 'Ir',
    'Yes': 'Sim',
    'OK': 'OK',
    'No': 'Não',
    'Save': 'Guardar',
    'Save data': 'Guardar os dados',
    'Ignore and close': 'Ignorar e fechar',
    'Drop data changes': 'Descartar a alteração de dados',
    'Manage': 'Gerir',
    'Select': 'Selecionar',
    'Configure': 'Configurar',
    
    'Show context help': 'Mostrar a ajuda de contexto',
    'Heurist context help': 'Ajuda contextual do Heurist',
    'Sorry context help was not found': 'Não foi possível encontrar a ajuda contextual',
    
    //main menu 
    'Admin': 'Administrar',
    'Design': 'Desenho',
    'Populate': 'Preencher',
    'Explore': 'Explorar',
    'Publish': 'Publicar',
    'Profile': 'Perfil',
    
    add_new_record: 'Novo', //in main menu - header
    add_new_record2: 'Novo', //in main menu - prefix add new record
    
    //explore menu
    'Filters': 'Filtros',
    'Recent': 'Recentes',
    'All': 'Todos',
    'Entities': 'Entidades',
    'Saved filters': 'Filtros gardados',
    'Build': 'Construir',
    'Filter builder': 'Construtor de filtros',
    'Facets builder': 'Construtor de facetas',
    'Save filter': 'Gardar o filtro',
    'Advanced': 'Avançado',
    'Rules': 'Regras',
    'Favourites': 'Favoritos',
    
    //admin menu
    'Database': 'Base de dados',
        menu_database_browse: 'Abrir',
        menu_database_create: 'Novo',
        menu_database_clone: 'Clonar',
        menu_database_delete: 'Apagar',
        menu_database_clear: 'Limpar',
        menu_database_rollback: 'Desfazer',
        menu_database_register: 'Registar',
        menu_database_properties: 'Propriedades',
        
        menu_database_browse_hint:'Abrir e fazer login em outra base de dados Heurist - a base de dados atual permanece aberta',
        menu_database_create_hint:'Criar uma nova base de dados no servidor atual - os elementos estruturais essenciais são preenchidos automaticamente',
        menu_database_clone_hint:'Clona uma base de dados a partir da base de dados atual com todos os dados, utilizadores, ficheiros anexos, modelos, etc.',
        menu_database_delete_hint:'Elimina completamente a base de dados atual - isto não pode ser desfeito, embora os dados sejam copiados para uma cópia de segurança que poderá ser recarregada por um administrador do sistema',
        menu_database_clear_hint:'Limpa todos os dados (registos, valores, ficheiros anexos) da base de dados atual. A estrutura da base de dados - tipos de registo, campos, termos, restrições, etc. - não é afetada',
        menu_database_rollback_hint:'Reverte seletivamente os dados na base de dados para uma data e hora específicas',
        menu_database_register_hint:'Regista esta base de dados no Índice de Referência Heurist - isto torna a estrutura (mas não dados) disponíveis para importação por outras bases de dados',
        menu_database_properties_hint:'Editar os metadados internos que descrevem a base de dados e definir alguns comportamentos globais. Recomendado para fornecer uma base de dados auto-documentada',
    
    'Manage Users': 'Gerir os utilizadores',
        menu_profile_groups: 'Grupos de trabalho',
        menu_profile_users: 'Utilizadores',
        menu_profile_import: 'Importar um utilizador',
        menu_profile_groups_hint: 'Lista de grupos em que é membro',
        menu_profile_users_hint: 'Adicione e edite os utilizadores da base de dados, incluindo a autorização de novos utilizadores. Utilize Grupos de Trabalho de Gestão para atribuir utilizadores a grupos de trabalho e definir funções dentro de grupos de trabalho',
        menu_profile_import_hint: 'Importar utilizadores um de cada vez a partir de outra base de dados do sistema, incluindo nome de utilizador, senha e endereço de e-mail - os utilizadores NÃO são atribuídos a grupos de trabalho',
    'Utilities': 'Utilidades',
        menu_profile_files: 'Gerir ficheiros',
        menu_profile_preferences: 'As minhas preferências',
        menu_structure_verify: 'Verificar a integridade',
        menu_structure_duplicates: 'Encontrar registos duplicados',
        menu_manage_rectitles: 'Reconstruir os títulos de registos',
        menu_manage_calcfields: 'Reconstruir os campos calculados',
        menu_interact_log: 'Registo de interação',
        menu_profile_files_hint: 'Gerir os ficheiros carregados e os recursos de média remoto',
        menu_profile_preferences_hint: 'Mostrar as preferências relativas a comportamentos relacionados com esta base de dados',
        menu_structure_verify_hint: 'Encontrar erros na estrutura da base de dados (códigos de registo inválidos, códigos de campo e de termo) e registos com estrutura incorreta ou valores inconsistentes (ponteiro inválido, dados em falta, etc.)',
        menu_structure_duplicates_hint: 'Pesquisa difusa para identificar registos que podem conter dados duplicados',
        menu_manage_rectitles_hint: 'Reconstrui os títulos construídos de registos listados nos resultados de pesquisa, para todos os registos',
        menu_interact_log_hint: 'Descarregue registo de interação',
    'Server Manager': '',
        menu_admin_server: 'Gerir as bases de dados',
        menu_admin_server_hint: 'Gerir as bases de dados',

    //design menu
    'Modify': 'Modificar',
        menu_structure_rectypes: 'Tipos de registo',
        menu_import_csv_rectypes: 'Importar de CSV',
        menu_structure_vocabterms: 'Vocabulários',
        menu_structure_fieldtypes: 'Campos básicos',
        menu_import_csv_fieldtypes: 'Importar de CSV',
        menu_structure_import: 'Navegue nos modelos',
        menu_structure_workflowstages: 'Estágios do fluxo de trabalho',
        menu_lookup_config: 'Procuras externas',
        menu_structure_summary: 'Visualizar',
        menu_structure_refresh: 'Refrescar a memória',
    
        menu_structure_rectypes_hint: 'Adicione novos tipos de registo e modifique os existentes',
        menu_structure_vocabterms_hint: 'Adicione e modifique Vocabulários &amp; Termos',
        menu_structure_fieldtypes_hint: 'Pesquise e edite as definições de campos básicos referenciadas por tipos de registo (muitas vezes partilhadas por vários tipos de registo)',
        menu_structure_import_hint: 'Importe seletivamente tipos de registos, campos, termos e tipos de registos conectados; de outras bases de dados Heurist',
        menu_structure_summary_hint: 'Visualize as ligações internas entre tipos de registo na base de dados e adicionar ligações (ponteiros de registo e marcadores de relacionamento) entre eles',
        menu_structure_refresh_hint: 'Limpar e recarregar a memória interna do Heurist do seu navegador. Use isto para corregir os menus desdobráveis, etc., se as alterações ou adições recentes não se mostram.',

    'Setup': 'Configuração',
        menu_manage_dashboards: 'Barra de atalhos',
        menu_manage_archive: 'Pacote de arquivo',
        menu_manage_dashboards_hint: 'Define uma lista editável de atalhos para funções a serem exibidas numa barra de ferramentas no arranque, a menos que seja desligado',
        menu_manage_archive_hint: 'Escreve todos os dados na base de dados como ficheiros SQL e XML, além de todos os ficheiros, esquemas e documentação anexados; para um ficheiro ZIP, que pode descarregar a partir de uma hiperligação',
    
    'Download': 'Descarregar',
        menu_manage_structure_asxml: 'Estrutura (XML)',
        menu_manage_structure_assql: 'Estrutura (Texto)',
        menu_manage_structure_asxml_hint: 'Lista as definições de tipo de registo e de campo em formato XML (HML - Heurist Markup Language)',
        menu_manage_structure_assql_hint: 'Lista as definições de tipo de registo e de campo num formato SQL legível por computador (desatualizado em 2014)',

    //populate menu
    'Upload Files': 'Carregar ficheiros',
        menu_import_csv: 'Texto delimitado / CSV',
        menu_import_zotero: 'Sincronização de bibliografia no Zotero',
        menu_import_xml: 'Heurist XML/JSon',
        menu_import_kml: 'KML',
        menu_import_get_template: 'Modelo de descarga (XML)',
        menu_import_csv_hint: 'Importar dados de ficheiros de texto delimitados. Suporta a localização de correspondências entre dados, a criação de registos e a atualização deles.',
        menu_import_zotero_hint: 'Sincronização com uma biblioteca web Zotero _ os novos registos são adicionados ao Heurist, os registos existentes são atualizados',
        menu_import_xml_hint: 'Importar registos de outra base de dados',
        menu_import_kml_hint: 'Importar ficheiros KML (dados geográficos em WKT podem ser importados a partir de ficheiros CSV &amp; e delimitados por tabulações)',
        menu_import_get_template_hint: 'Obtenha o modelo XML que descreve a estrutura da base de dados', 
    'Media Files': 'Ficheiros multimédia',
        menu_files_upload: 'Carregar ficheiros de média',
        menu_files_index: 'Indexar os ficheiros de média',
        menu_files_upload_hint: 'Faça o upload de vários ficheiros ou de ficheiros grandes para liberar espaço ou diretórios de imagens, bem como para eliminar e renomear os ficheiros carregados',
        menu_files_index_hint: 'Indexe os ficheiros no servidor e crie registos multimédia para eles (lê e cria manifestos FieldHelper)',
        
    //publish menu
    'Website': 'Website',
        menu_cms_create: 'Criar',
        menu_cms_edit: 'Editar',
        menu_cms_view: 'Ver',
        menu_cms_embed: 'Página web independente',
        menu_cms_create_hint: 'Criar um novo website CMS, armazenado nesta base de dados, com widgets para aceder aos dados na base de dados',
        menu_cms_edit_hint: 'Editar um website CMS definido nesta base de dados',
        menu_cms_view_hint: 'Ver um website CMS definido nesta base de dados',
        menu_cms_embed_hint: 'Crie ou edite uma página web independente nesta base de dados. Pode conter widgets Heurist. Fornece código para incorporar a página em um iframe externo.',
        
        'Website Editor': 'Editor de websites',
        'Sitewide Settings': 'Definições globais do website',
        'Current Page': 'Página atual',
        'Page Editor': 'Editor de páginas',
        
    'Standalone web page': 'Página web independente',
        menu_cms_create_page: 'Criar',
        menu_cms_edit_page: 'Editar',
        menu_cms_view_page: 'Ver',
        
    'Export': 'Exportar',
        menu_export_csv: 'CSV',
        menu_export_hml_resultset: 'XML',
        menu_export_json: 'JSON',
        menu_export_geojson: 'GeoJSON',
        menu_export_kml: 'KML',
        menu_export_gephi: 'GEPHI',
        menu_export_iiif: 'IIIF',
        menu_export_csv_hint: 'Exportar os registos como texto delimitado (por vírgulas ou tabulações), aplicando o tipo de registo',
        menu_export_hml_resultset_hint: 'Gerar um ficheiro HML (formato Heurist XML) para o conjunto atual de resultados de pesquisa (consulta atual + expansão)',
        menu_export_json_hint: 'Gerar um ficheiro JSON para o conjunto atual de resultados de pesquisa (consulta atual)',
        menu_export_geojson_hint: 'Gerar um ficheiro GeoJSON_T para o conjunto atual de resultados de pesquisa (consulta atual)',
        menu_export_kml_hint: 'Gerar um ficheiro KML para o conjunto atual de resultados de pesquisa (consulta atual + expansão)',
        menu_export_gephi_hint: 'Gerar um ficheiro GEPHI para o conjunto atual de resultados de pesquisa (consulta atual + expansão)',
        menu_export_iiif_hnt: 'Gerar um manifesto IIIF para o conjunto atual de resultados de pesquisa',
        
    'Safeguard':'Salvaguarda',

    //My profile menu
    menu_profile_tags: 'Gerir as etiquetas',
    menu_profile_reminders: 'Gerir os lembretes',
    menu_profile_info: 'A minha informação de utilizador/a',
    menu_profile_logout: 'Terminar a sessão',
    menu_profile_tags_hint: 'Apague, combine, mude o nome das suas etiquetas pessoais e de grupo de trabalho',
    menu_profile_reminders_hint: 'Ver e apagar os e-mails de notificação automática enviados a partir dos registos que marcou',
    menu_profile_info_hint: 'Informação pessoal do/a utilizador/a',
    menu_profile_logout_hint: '',
        
    //HELP menu
    HELP: 'AJUDA',
        menu_help_online: 'Ajuda online',
        menu_help_website: 'Website da Rede Heurist',
        menu_help_online_hint: 'Ajuda online abrangente para o sistema Heurist',
        menu_help_website_hint: 'Website da Rede Heurist - uma fonte para uma vasta gama de informações, serviços e contactos', 
    CONTACT: 'CONTACTO',
        menu_help_bugreport: 'Relatório de erros/pedido de funcionalidades',
        menu_help_emailteam: 'Equipa Heurist',
        menu_help_emailadmin: 'Administrador do Sistema',
        menu_help_acknowledgements: 'Agradecimentos',
        menu_help_about: 'Sobre',
        menu_help_bugreport_hint: 'Envie um e-mail para a equipa Heurist, com informações adicionais sobre erros, comentários ou pedidos de novas funcionalidades',
        menu_help_emailteam_hint: '',
        menu_help_emailadmin_hint: 'Envie um e-mail para o administrador do sistema para esta instalação do Heurist - normalmente para resolver problemas com esta instalação',
        menu_help_acknowledgements_hint: '',
        menu_help_about_hint: 'Versão, Créditos, Direitos autorais, Licença',
    
    menu_subset_set: 'Definido como subconjunto',
    menu_subset_set_hint: 'Faça do filtro atual o subconjunto ativo ao qual todas as ações subsequentes são aplicadas',
    'Click to revert to whole database': '',
    'SUBSET ACTIVE': 'SUBCONJUNTO ATIVO',
    'Current subset': 'Subconjunto atual',
    
    //END main menu
    
    //resultList --------------------
    
    'Selected': 'Selecionados',
        menu_selected_select_all: 'Selecionar tudo',
        menu_selected_select_all_hint: 'Selecione Tudo na página',
        menu_selected_select_none: 'Não selecionar nada',
        menu_selected_select_none_hint: 'Limpar a seleção',
        menu_selected_select_show: 'Mostrar como pesquisa',
        menu_selected_select_show_hint: 'Lançar os registos selecionados numa nova janela de pesquisa',
        menu_selected_tag: 'Etiqueta',
        menu_selected_tag_hint: 'Selecione um ou mais registos e, em seguida, clique para adicionar ou remover etiquetas',
        menu_selected_rate: 'Avaliação',
        menu_selected_rate_hint: 'Selecione um ou mais registos e, em seguida, clique para definir classificações',
        menu_selected_bookmark: 'Marcar como favorito',
        menu_selected_bookmark_hint: 'Selecione um ou mais registos e, em seguida, clique em Marcar como favorito',
        menu_selected_unbookmark: 'Deselecionar como favorito',
        menu_selected_unbookmark_hint: 'Selecione um ou mais registos e, em seguida, clique para remover os marcadores',
        menu_selected_merge: 'Combinar',
        menu_selected_merge_hint: 'Selecione um ou mais registos e, em seguida, clique para identificar/corrigir duplicados',
        menu_selected_delete: 'Apagar',
        menu_selected_delete_hint: 'Selecione um ou mais registos e, em seguida, clique para os eliminar',
    'Collected': 'Coletados',
    'Collect': 'Coletar',
        menu_collected_add: 'Adicionar',
        menu_collected_add_hint: 'Selecione um ou mais registos e, em seguida, clique para os adicionar à coleção',
        menu_collected_del: 'Remover',
        menu_collected_del_hint: 'Selecione um ou mais registos e, em seguida, clique para remover esses registos da coleção',
        menu_collected_clear: 'Limpar tudo',
        menu_collected_clear_hint: 'Vaziar os registos da coleção',
        menu_collected_save: 'Guardar como...',
        menu_collected_save_hint: 'Guarde o conjunto atual de registos na coleção',
        menu_collected_show: 'Mostrar como pesquisa',
        menu_collected_show_hint: 'Mostrar o conjunto atual de registos na coleção',
            collection_limit: 'O número de registos selecionados está acima do limite em ',
            collection_select_hint: 'Selecione no mínimo um registo para adicionar ao espaço de recolha',
            collection_select_hint2: 'Por favor, selecione pelo menos um registo para remover do espaço de recolha',
            collection_url_hint: 'As coleções são guardadas como uma lista de identificadores (IDs) de registos. O URL gerado por esta coleção excederia o comprimento máximo admissível de URL de 2083 caracteres. Guarde a coleção atual como uma pesquisa guardada (que permite um número muito maior de registos) ou adicione menos registos.',
        
    'Recode': 'Recodificar',
        menu_selected_value_add: 'Adicionar valor de campo',
        menu_selected_value_add_hint: 'Adicione um valor de campo aos registos filtrados',
        menu_selected_value_replace: 'Substituir valor de campo',
        menu_selected_value_replace_hint: 'Substitua o valor de campo encontrado nos registos filtrados',
        menu_selected_value_delete: 'Eliminar valor de campo',
        menu_selected_value_delete_hint: 'Elimine o valor do campo encontrado nos registos filtrados',
        menu_selected_add_link: 'Relacionar: Link',
        menu_selected_add_link_hint: 'Adicione um novo link ou crie uma nova relação entre registos',
        menu_selected_rectype_change: 'Alterar tipos de registo',
        menu_selected_rectype_change_hint: 'Altere os tipos de registo para os registos filtrados',
        menu_selected_extract_pdf: 'Extrair texto de ficheiros PDF',
        menu_selected_extract_pdf_hint: 'Extrair texto de PDFs (experimental)',
    'Share': '',
        menu_selected_notify: 'Notificação (e-mail)',
        menu_selected_notify_hint: 'Selecione um ou mais registos e, em seguida, clique para enviar uma notificação',
        menu_selected_email: 'Enviar email',
        menu_selected_email_hint: 'Envie uma mensagem de correio eletrónico para os indivíduos ou organizações descritos no registo selecionado',
        menu_selected_ownership: 'Propriedade / visibilidade',
        menu_selected_ownership_hint: 'Selecione um ou mais registos e, em seguida, clique para definir a propriedade e a visibilidade do grupo de trabalho',
    'Reorder': 'Reordenar',
    menu_reorder_hint: 'Permite o reordenamento manual dos resultados atuais e guardá-los como uma lista fixa de registos ordenados',
    menu_reorder_title: 'Arraste os registos acima ou abaixo para a posição desejada, em seguida, guarde como uma lista fixa nessa ordem',
    menu_reorder_save: 'Guardar a ordem',
    
    //
    resultList_select_record: 'Por favor, selecione pelo menos um registo para a ação',
    resultList_select_record2: 'Por favor, selecione pelo menos dois registos para identificar/combinar registos duplicados',
    resultList_select_limit: 'O número de registos selecionados está acima do limite em ',
    resultList_noresult: 'Não foram encontrados resultados. Por favor, modifique a pesquisa/filtro para devolver pelo menos um registo nos resultados.',
    resultList_empty_remark: 'Nenhuma entrada corresponde aos critérios do filtro (as entradas podem existir, mas podem não ter sido tornadas visíveis ao público ou ao seu perfil de utilizador)',
    resultList_private_record: 'Este registo não é visível publicamente - o utilizador deve estar identificado no sistema para vê-lo',
    'private - hidden from non-owners': 'privado - oculto para não proprietários',
    'visible to any logged-in user': 'visível para todos os utilizadores identificados',
    'pending (viewable by anyone, changes pending)': 'pendente (visível para todos, alterações pendentes)',
    'public (viewable by anyone)': 'público (visível para todos)',
    'show selected only': 'mostrar apenas os selecionados',
    'Single lines': 'Linhas únicas',
    'Single lines with icon': 'Linhas únicas com ícone',
    'Small images': 'Imagens pequenas',
    'Large image': 'Imagem grande',
    'Record contents': 'Conteúdos do registo',
    resultList_view_content_hint1: 'Este aviso é desencadeado se houver mais de 10 registos',
    resultList_view_content_hint2: 'Você selecionou',
    resultList_view_content_hint3: 'registos. Este modo de exibição carrega informações completas para cada registo e levará muito tempo a carregar e a exibir todos estes dados.',
    resultList_action_edit: 'Clique para editar o registo',
    resultList_action_edit2: 'Clique para editar o registo (abre em novo separador)',
    resultList_action_view: 'Clique para ver o disco (abre em janela emergente)',
    resultList_action_view2: 'Clique para ver o link externo (abre em nova janela)',
    resultList_action_map: 'Clique para mostrar/esconder no mapa',
    resultList_action_dataset: 'Descarregar o conjunto de dados',
    resultList_action_embded: 'Clique para incorporar',
    resultList_action_delete: 'Clique para excluir',
    
    resultList_action_delete_hint: 'Apague o documento do mapa. As camadas de mapas associadas e as fontes de dados retêm',
    'Password reminder': 'Lembrete de senha',
    resultList_reorder_list_changed: 'A lista de reordenação foi alterada.',
    //END resultList
    
    //search - filters
    'Filter': 'Filtrar',
    'Filtered Result': 'Resultado filtrado',
    'Save Filter': 'Gardar o filtro',
    save_filter_hint: 'Guarde o filtro e as regras atuais como um link na árvore de navegação',
     
    //edit 
    Warn_Lost_Data: 'Fez alterações nos dados. Clique em "Guardar", caso contrário todas as alterações serão perdidas.',     
    Warn_Lost_Data_On_Structure_Edit: 'Clique em "Guardar os dados" para guardar os dados introduzidos ou em "Descartar as alterações de dados" para abandonar as modificações.<br>As alterações de estrutura são guardadas automaticamente - não são afetadas pela sua escolha.',
    
    //manage db definitions    
    manageDefRectypes_edit_fields: 'Editar campos',
    manageDefRectypes_edit_fields_hint: 'Isto abrirá um registo em branco deste tipo no modo de modificação da estrutura.<br>Sugestão: Pode ligar o modo de modificação da estrutura a qualquer momento enquanto introduz os dados para então fazer alterações estruturais instantâneas',            
    manageDefRectypes_new_hint: 'Antes de definir novos tipos de registo (entidade) sugerimos importar entre os adequados '+
                    'definições de modelos (bases de dados Heurist registadas no centro de coordenação Heurist). '+
                    'Aqueles modelos cujos ID de registo são menores do que 1000, são modelos geridos pela equipa Heurist. '
                    +'<br><br>'
    +'Isto é particularmente importante para os tipos de registo BIBLIOGRAPHIC - as definições no modelo n.º 6 (definições bibliográficas) são ' 
    +'normalizados de maneira ótima e garantem a compatibilidade com funções bibliográficas tais como a sincronização com o Zotero, o formato Harvard e a compatibilidade entre bases de dados.'                
                    +'<br><br>Use o menu principal: Desenho > Navegar nos modelos',
    manageDefRectypes_delete_stop: '<p>O tipo de <b>registo [rtyName]</b> é referenciado pelos seguintes campos:</p>'
                + '[FieldList]'
                +'<p>Por favor, remova estes campos completamente, ou clique nos links acima <br>para modificar o campo base (afetará todos os tipos de registo que o utilizam).</p>',
    manageDefRectypes_delete_warn: 'Tem a certeza de que deseja eliminar este tipo de registo?',
    manageDefRectypes_duplicate_warn: 'Quer mesmo duplicar o tipo de registo ',
    'Click to add new': 'Clique para adicionar um novo',
    'Click to launch search for': 'Clique para lançar uma pesquisa',
    'Click to edit record type': 'Clique para editar o tipo de registo',
    'Click to hide in lists': 'Clique para ocultar nas listas',
    'Click to show in lists': 'Clique para mostrar nas listas',
    'Duplicate record type': 'Duplicar o tipo de registro',
    'List of fields': 'Lista de campos',
    manageDefRectypes_reserved: 'Este é um tipo de registo reservado comum a todas as bases de dados Heurist. Não pode ser apagada.',
    manageDefRectypes_hasrecs: 'Para permitir a eliminação, utilize Explorar > Entidades para encontrar e apagar todos os registos.',
    manageDefRectypes_delete: 'Clique para eliminar este tipo de registo',
    manageDefRectypes_referenced: 'Este tipo de registo é referenciado. Clique para mostrar as referências',
    manageDefRectypes_longrequest: 'Isto está a demorar muito tempo, é possível que a base de dados não seja acessível em [url], continuaremos a tentar por mais 10 segundos',
    'Filter by Group': 'Filtrar por grupo',
    'Show All': 'Mostrar tudo',
    'Sort by': 'Ordenar por',
    'All Find': 'Todos os localizados',
    'Not finding the record type you require?': 'Não encontrou o tipo de registo que precisa?',
    'Define new record type': 'Definir um ovo tipo de registo',
    'Find by name, ID or concept code': 'Encontre pelo nome, ID ou código de conceito',
    'Show record types for all groups': 'Mostrar tipos de registos para todos os grupos',
    'All Groups': 'Todos os grupos',
    'All record type groups': 'Todos os grupos de tipo de registo',
    'Edit Title Mask': 'Editar a máscara de título',
                    
    //my preferences
    'Define Symbology': 'Definir a simbologia',                
    'Define Heurist Theme': 'Definir o tema Heurist',
    'Configure Interface': 'Configurar a interface',
    'Mark columns to be visible. Drag to re-order': 'Marque as colunas para serem visíveis. Arraste para ordenar',
    
    //record add dialog    
    'Add Record': 'Adicionar Registo',
    'Record addition settings': 'Definições de adição de registos',
    'Permission settings': 'Definições de permissão',
    'Save Settings': 'Guardar as definições',
    'Add Record in New Window': 'Adicionar um registro em nova janela',
    'Type of record to add': 'Tipo de registo para adicionar',
    'Get Parameters': 'Obter parâmetros',
    'Select record type for record to be added': 'Selecione o tipo de registo para o registo a ser adicionado',
    'select record type': 'selecionar tipo de registo',
    add_record_settings_hint: 'Estas definições serão aplicadas e lembradas quando selecionar um tipo de registo da lista',

    //record actions dialogs
    'Processed records': 'Registos processados',
    recordAction_select_lbl: 'Âmbito de registos:',
    recordAction_select_hint: 'por favor, selecione os registos a serem afetados...',    
        'All records': 'Todos os registros',
        'Selected results set (count=': 'Conjunto de resultados selecionados (total=',
        'Current results set (count=': 'Conjunto de resultados atuais (total=',
        'only:': 'só:',
    'Add or Remove Tags for Records': 'Adicionar ou remover etiquetas para registos',
        'Add tags': 'Adicionar etiquetas',
        'Remove tags': 'Remover etiquetas',
        'Bookmark': 'Marcar como favorito',
        recordTag_hint0: 'Selecione as etiquetas a adicionar aos marcadores para os URLs selecionados<br>',
        recordTag_hint1: 'Selecione as etiquetas a adicionar aos marcadores para o âmbito de registos escolhido<br>',
        recordTag_hint2: 'Selecione as etiquetas a adicionar ou remover para o âmbito de registos escolhido<br>',
        recordTag_hint3: 'As etiquetas correspondentes são mostradas à medida que escreve. Clique numa etiqueta listada para adicioná-la.<br>As etiquetas não reconhecidas são adicionadas automaticamente como etiquetas específicas do utilizador <br>(as etiquetas de grupo devem ser explicitamente adicionadas por um administrador de grupo). As etiquetas podem conter espaços.',
        'No tags were affected': 'Nenhuma etiqueta foi afetada',
        'Bookmarks added': 'Marcadores adicionados',
        'Tags added': 'Etiquetas adicionadas',
        'Tags removed': 'Etiquetas removidas',
    'Unbookmark selected records': 'Desmarcar os registos selecionados',    
        recordUnbookmark_hint: 'Selecione o âmbito de registos com marcadores a serem removidos.<br>'
            +'Quaisquer etiquetas pessoais para estes registos serão separadas <br>'
            +'Esta operação SÓ remove o marcador dos seus recursos, <br>'
            +'não apaga as entradas de registo<br>',
        'Remove Bookmarks': 'Remover marcadores',
        'Bookmarks removed': 'Marcadores removidos',
    'Set Record Rating': 'Definir a classificação de registos',
        'Set Rating': 'Definir a classificação',
        'Please specify rating value': 'Por favor, especifique o valor da classificação',
        'Rating updated': 'Classificação atualizada',
        'No Rating': 'Nenhuma classificação',
    'Delete Records': 'Excluir Registos',
    
    //SERVER SIDE ERROR MESSAGES
    'Record type not defined or wrong': 'Tipo de registo não definido ou errado',
    
    //Client side error message
    Error_Title: 'Heurist',
    Error_Empty_Message: 'Nenhuma mensagem de erro foi fornecida, por favor informe os desenvolvedores do Heurist.',
    Error_Report_Code: 'Informar este código à equipa Heurist',
    Error_Report_Team: 'Se este erro ocorrer repetidamente, contacte o administrador do seu sistema ou envie-nos um e-mail (support em HeuristNetwork dot org) e descreva as circunstâncias em que ocorre para que possamos encontrar uma solução',
    Error_Wrong_Request: 'O valor, número e/ou conjunto de parâmetros solicitados não é válido.',
    Error_System_Config: 'Pode resultar de uma falha de rede, ou porque o sistema não está devidamente configurado. Se o problema persistir, por favor informe o administrador do seu sistema',
    Error_Json_Parse: 'Não é possível analisar a resposta do servidor',
    
//---------------------- END OF TRANSLATION 2021-10-19
//===================    
    
    'Design database': 'Desenhar base de dados',
    'Import data': 'Importar dados',
    'Please log in':'Faça o login ou registe-se para utilizar todas as funcionalidades do Heurist.',
    'Session expired': 'Parece que não está a iniciar sessão ou a sessão expirou. Por favor, recarregue a página para iniciar sessão novamente',
    'Please contact to register':'Contacte o proprietário da base de dados para registar e utilizar todas as funcionalidades do Heurist.',
    'My Searches':'Os meus filtros',
    'My Bookmarks':'Os meus favoritos',

    'Password_Reset':'A sua palavra-passe foi reposta. Deverá receber um e-mail em breve com a sua nova senha.',

    'Error_Password_Reset':'Não foi possível completar a operação.',
    'Error_Mail_Recovery':'O seu email de recuperação de palavras-passe não pode ser enviado uma vez que o sistema de correio eletrónico SMTP não foi devidamente instalado neste servidor. Por favor, peça ao administrador do seu sistema para corrigir a instalação.',
    'Error_Mail_Registration':'A sua informação de registo é adicionada à base de dados. No entanto, não é possível aprová-lo uma vez que o e-mail de inscrição não pode ser enviado.',
    'Error_Mail_Approvement':'Não é possível enviar o e-mail de aprovação de registo - por favor contacte os desenvolvedores do Heurist.',
    'Error_Connection_Reset':'Tempo limite na resposta do servidor Heurist.<br><br>'
    +'Isto pode ser devido a uma falha na Internet (a fonte mais comum), ou devido à carga do servidor ou ao pedido de um conjunto de resultados demasiado grande, ou a uma consulta que não resolve. '
    +'Se o problema persistir, por favor envie um relatório de erro à equipa Heurist para que possamos investigar.',

    'New_Function_Conversion':'Esta função ainda não foi convertida da versão Heurist 4 para a versão 5 (2018). ',
    
    'New_Function_Contact_Team':'Se precisar desta opção, por favor envie um correio para o endereço heuristnetwork dot org ou ou '
    +'clique na entrada do relatório de Erro/Pedido de funcionalidades no menu Ajuda - é possível termos já uma versão alfa '
    +'que poderá usar ou poderá dar prioridade ao desenvolvimento.',
    
    //OLD VERSION    No response from server. This may be due to too many simultaneous requests or a coding problem. Please report to Heurist developers if this reoccurs.',

    'mailto_fail': 'Não definiu um endereço de correio para mailto: links. '
    +'<br/>Por favor, defina isto no seu navegador (normalmente está nas definições de conteúdo em Privacidade).',

    //titles
    'add_record':'Adicionar um novo registo',
    'add_detail':'Adicionar valor de campo',
    'replace_detail':'Substituir valor de campo',
    'delete_detail':'Eliminar valor de campo',
    'rectype_change':'Alterar o tipo de registro (entidade)',
    'ownership':'Alterar o acesso e a propriedade do registo',
    'add_link':'Adicionar um novo link ou criar uma relação entre registos',
    'extract_pdf':'Extrair texto de um PDF',

    //helps
    'record_action_add_record':' ',
    'record_action_add_detail':'Esta função adiciona um novo valor a um campo específico do conjunto de registos '
    +'selecionado no menu desdobrável. Os valores existentes não são afetados. A adição irá adicionar valores mesmo que este '
    +'exceda a contagem de repetição válida (por exemplo, mais do que um valor para campos de valor único) - verifique a base de dados '
    +'posteriormente para validação, utilizando Verificar > Verificar a integridade.',
    
    'record_action_replace_detail':'Esta função substitui o valor especificado em um campo em concreto para o  '
    +'conjunto de registos selecionados no menu desdobrável. Outros campos/valores não são afetados.',
    
    'record_action_delete_detail':'Esta função elimina o valor especificado em um campo em concreto para o '
    +'conjunto de registos selecionados no menu desdobrável. Outros campos/valores não são afetados. '
    +'A eliminação removerá valores incluindo o último valor num campo obrigatório - verifique a base de dados '
    +'posteriormente para validação, utilizando Verificar > Verificar a integridade.',
    
    'record_action_rectype_change':('Esta função altera o tipo de registo (entidade) do conjunto de registos '
    +'selecionado no menu desdobrável. Todos os registos selecionados serão convertidos para o novo tipo.'
        +'<br><br>'
        +'<b>Aviso</b>: É altamente provável que os dados registados para muitos ou todos estes registos '
        +'não satisfaçam as condições estabelecidas para o tipo de registo a que são convertidos. '
        +'<br><br>'
        +'Utilize a opção de menu Verificar > Verificar a integridade para localizar e corrigir quaisquer registos inválidos gerados por este processo.'),    
    'record_action_ownership':'&nbsp;',
    'record_action_extract_pdf':('Esta função extrai texto (até 64.000 caracteres) de quaisquer ficheiros PDF anexados a um registo e coloca o texto extraído no campo especificado (por defeito "Texto extraído" (2-652), se definido). Os caracteres incorretos encontrados são ignorados.'
        +'<br><br>'
+'Se houver mais de um ficheiro PDF, o texto é colocado em valores repetidos do campo. '
        +'<br><br>'
+'O texto só é extraído se o (valor correspondente) do campo estiver vazio para evitar a sobreposição de qualquer texto introduzido manualmente.'),
    
    //reports
    'record_action_passed': 'Registos passados para o processo',
    'record_action_noaccess': 'Registos não editáveis encontrados (pode ser devido às permissões)',
    'record_action_processed': 'Registos processados',
    'record_action_undefined_add_detail': 'Registos com campos indefinidos',
    'record_action_undefined_replace_detail': 'Ignorado devido a não localizar nenhuma correspondência',
    'record_action_undefined_delete_detail': 'Ignorado devido a não localizar nenhuma correspondência',
    'record_action_limited_add_detail': 'Ignorado por ter ultrapassado o limite de valores repetidos',
    'record_action_limited_delete_detail': 'Ignorado porque o campo exigido não pode ser eliminado',
    
    'record_action_undefined_extract_pdf': 'Registos sem PDF associado',
    'record_action_parseexception_extract_pdf': 'Registos com PDFs bloqueados (extração proibida)',
    'record_action_parseempty_extract_pdf': 'Registos sem texto para extrair (por exemplo, imagens)',
    'record_action_limited_extract_pdf': 'Registos com campo já preenchido',
    
    'record_action_errors': 'Erros',

    'thumbs3':'pré-visualizar',
    
    'Collected':'Coletar',
    'Shared':'Compartilhar',

};



