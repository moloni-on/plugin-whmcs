<?php

/**
 * Traduções em português para o módulo Moloni ON do WHMCS.
 *
 * Perspetiva na segunda pessoa, tratando o utilizador por "tu".
 *
 * @return array<string,string>
 */

declare(strict_types=1);

return [
    // Layout / navegação
    'module_name' => 'Moloni ON',
    'current_company' => 'Empresa',
    'nav_orders' => 'As tuas encomendas',
    'nav_documents' => 'Os teus documentos',
    'nav_discarded' => 'Descartadas',
    'nav_settings' => 'As tuas configurações',
    'nav_tools' => 'Ferramentas',
    'nav_logs' => 'Os teus registos',
    'nav_logout' => 'Terminar sessão',
    'footer_help_lead' => 'Precisas de ajuda?',
    'footer_help_link' => 'Consulta os nossos guias.',

    // Início de sessão
    'login_title' => 'Ligar ao Moloni ON',
    'login_intro' => 'Introduz as tuas credenciais de API do Moloni ON para autorizar esta instalação do WHMCS.',
    'developer_id' => 'ID de cliente da API',
    'client_secret' => 'Segredo do cliente',
    'connect' => 'Ligar',
    'credentials_required' => 'Indica o ID de cliente e o segredo da API.',

    // Seleção de empresa
    'company_title' => 'Seleciona uma empresa',
    'company_none' => 'Não tens empresas disponíveis nesta conta.',
    'select_company' => 'Selecionar empresa',

    // Encomendas
    'orders_title' => 'Encomendas pendentes',
    'orders_empty' => 'Não tens encomendas pendentes de sincronização.',
    'create_selected' => 'Criar documentos para as selecionadas',
    'create' => 'Criar',
    'discard' => 'Descartar',
    'confirm_discard' => 'Queres descartar esta encomenda? Deixará de ser sincronizada.',
    'confirm_bulk' => 'Queres criar documentos para todas as encomendas selecionadas?',
    'status_pending' => 'Pendente',
    'status_failed' => 'Falhou',

    // Documentos
    'documents_title' => 'Documentos criados',
    'documents_empty' => 'Ainda não criaste documentos.',
    'download_pdf' => 'Descarregar PDF',
    'discarded_title' => 'Encomendas descartadas',
    'discarded_empty' => 'Não tens encomendas descartadas.',
    'revert' => 'Repor como pendente',

    // Colunas de tabela
    'col_order' => 'Encomenda',
    'col_customer' => 'Cliente',
    'col_amount' => 'Valor',
    'col_date' => 'Data',
    'col_status' => 'Estado',
    'col_actions' => 'Ações',
    'col_document' => 'N.º de documento',
    'col_total' => 'Total',
    'col_timestamp' => 'Data/hora',
    'col_level' => 'Nível',
    'col_message' => 'Mensagem',
    'col_context' => 'Contexto',

    // Tipos de documento
    'doctype_invoice' => 'Fatura',
    'doctype_receipt' => 'Recibo',
    'doctype_invoiceReceipt' => 'Fatura-Recibo',
    'doctype_simplifiedInvoice' => 'Fatura Simplificada',
    'doctype_proFormaInvoice' => 'Fatura Pró-forma',
    'doctype_purchaseOrder' => 'Nota de Encomenda',
    'doctype_estimate' => 'Orçamento',

    // Configurações
    'settings_title' => 'As tuas configurações',
    'setting_document_type' => 'Tipo de documento predefinido',
    'setting_document_status' => 'Estado do documento',
    'status_draft' => 'Rascunho',
    'status_closed' => 'Fechado',
    'setting_document_set' => 'Série de documentos',
    'setting_document_set_unavailable' => 'Séries indisponíveis (verifica a ligação)',
    'document_sets_unavailable' => 'Não foi possível carregar as séries do Moloni ON. Confirma que a tua empresa tem uma série definida no Moloni ON e recarrega esta página.',
    'setting_auto_create' => 'Criar automaticamente um documento quando uma fatura é paga',
    'setting_payment_method' => 'Método de pagamento predefinido',
    'setting_payment_method_help' => 'Usado quando o gateway de pagamento da encomenda não corresponde a nenhum método de pagamento do Moloni ON pelo nome. Só é adicionado a tipos de documento que suportam pagamentos.',
    'setting_send_email' => 'Enviar o documento por e-mail ao cliente após a criação',
    'setting_send_email_help' => 'Só se aplica quando o estado do documento é Fechado; os rascunhos nunca são enviados por e-mail.',
    'setting_option_none' => '— Nenhuma —',
    'settings_product_mapping' => 'Predefinições de mapeamento de artigos',
    'settings_product_mapping_help' => 'Predefinições usadas ao criar artigos no Moloni ON para as linhas da encomenda. Os impostos são obtidos automaticamente da taxa de IVA de cada encomenda.',
    'setting_measurement_unit' => 'Unidade de medida',
    'setting_product_category' => 'Categoria de artigo',
    'setting_exemption_reason' => 'Motivo de isenção de imposto',
    'setting_exemption_reason_help' => 'Motivo de isenção aplicado automaticamente a qualquer linha sem IVA (ex.: M07). Aparece como lista quando a tua zona fiscal define códigos de motivo; caso contrário, é introduzido como texto livre.',
    'settings_customer_mapping' => 'Cliente e zona fiscal',
    'setting_fiscal_zone_based_on' => 'Zona fiscal baseada em',
    'setting_fiscal_zone_based_on_help' => 'De onde vem a zona fiscal do documento. A faturação recorre à zona da empresa quando o cliente não tem país.',
    'fiscal_zone_company' => 'Zona fiscal da empresa',
    'fiscal_zone_billing' => 'País de faturação do cliente',
    'setting_vat_field' => 'Campo personalizado de NIF',
    'setting_vat_field_help' => 'Nome do campo personalizado de cliente WHMCS com o NIF. Deixa vazio para usar o NIF do cliente.',
    'save_settings' => 'Guardar configurações',
    'settings_saved' => 'Configurações guardadas.',

    // Ferramentas
    'tools_title' => 'Ferramentas',
    'tools_connection' => 'Ligação',
    'tools_connected' => 'Estás ligado a :company.',
    'tools_not_connected' => 'Não estás ligado a nenhuma empresa.',
    'tools_more' => 'Mais ferramentas',
    'tools_coming_soon' => 'Mais utilitários em breve.',

    // Registos
    'logs_title' => 'Os teus registos',
    'logs_all_levels' => 'Todos os níveis',
    'logs_empty' => 'Não tens registos.',
    'clear_logs' => 'Limpar registos antigos',
    'confirm_clear_logs' => 'Queres eliminar os registos com mais de uma semana? Não é possível anular esta ação.',
    'logs_cleared' => 'Registos limpos.',
    'view_context' => 'Ver',
    'log_context_title' => 'Contexto do registo',
    'close' => 'Fechar',

    // Paginação
    'pagination_label' => 'Paginação',
    'pagination_prev' => 'Anterior',
    'pagination_next' => 'Seguinte',
    'pagination_summary' => 'A mostrar :from–:to de :total',

    // OAuth / diversos
    'oauth_state_mismatch' => 'A autorização falhou: o token de segurança não corresponde. Tenta ligar novamente.',
    'redirecting' => 'A redirecionar para o Moloni ON…',
    'redirect_continue' => 'continuar',
    'pdf_download_failed' => 'Não foi possível transferir o PDF.',

    // Resultados de operações
    'order_discarded' => 'Encomenda descartada.',
    'order_reverted' => 'Encomenda reposta como pendente.',
    'document_created' => 'Documento criado (ID :id).',
    'document_skipped' => 'Encomenda ignorada: fatura de pagamento em massa, nada a faturar.',
    'document_failed' => 'Não foi possível criar o documento: :error',
    'no_orders_selected' => 'Não selecionaste nenhuma encomenda.',
    'bulk_result' => 'Criados :created documento(s); :skipped ignoradas; :failed falharam.',
];
