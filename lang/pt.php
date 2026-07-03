<?php

/**
 * Traduções em português para o módulo Moloni ON do WHMCS.
 *
 * Perspetiva na primeira pessoa, conforme a convenção do projeto.
 *
 * @return array<string,string>
 */

declare(strict_types=1);

return [
    // Layout / navegação
    'module_name' => 'Moloni ON',
    'current_company' => 'Empresa',
    'nav_orders' => 'As minhas encomendas',
    'nav_documents' => 'Os meus documentos',
    'nav_settings' => 'As minhas configurações',
    'nav_tools' => 'Ferramentas',
    'nav_logs' => 'Os meus registos',
    'nav_logout' => 'Terminar sessão',
    'footer_note' => 'Moloni ON para WHMCS',

    // Início de sessão
    'login_title' => 'Ligar ao Moloni ON',
    'login_intro' => 'Introduzo as minhas credenciais de API do Moloni ON para autorizar esta instalação do WHMCS.',
    'developer_id' => 'ID de cliente da API',
    'client_secret' => 'Segredo do cliente',
    'connect' => 'Ligar',
    'login_help' => 'Onde encontro as minhas credenciais de API?',
    'credentials_required' => 'Indico o ID de cliente e o segredo da API.',

    // Seleção de empresa
    'company_title' => 'Seleciono uma empresa',
    'company_none' => 'Não tenho empresas disponíveis nesta conta.',
    'select_company' => 'Selecionar empresa',

    // Encomendas
    'orders_title' => 'Encomendas pendentes',
    'orders_empty' => 'Não tenho encomendas pendentes de sincronização.',
    'create_selected' => 'Criar documentos para as selecionadas',
    'create' => 'Criar',
    'discard' => 'Descartar',
    'confirm_discard' => 'Descarto esta encomenda? Deixará de ser sincronizada.',
    'confirm_bulk' => 'Crio documentos para todas as encomendas selecionadas?',
    'status_pending' => 'Pendente',
    'status_failed' => 'Falhou',

    // Documentos
    'documents_title' => 'Documentos criados',
    'documents_empty' => 'Ainda não criei documentos.',
    'download_pdf' => 'Descarregar PDF',
    'discarded_title' => 'Encomendas descartadas',
    'discarded_empty' => 'Não tenho encomendas descartadas.',
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
    'doctype_billsOfLading' => 'Guia de Transporte',

    // Configurações
    'settings_title' => 'As minhas configurações',
    'setting_document_type' => 'Tipo de documento predefinido',
    'setting_document_status' => 'Estado do documento',
    'status_draft' => 'Rascunho',
    'status_closed' => 'Fechado',
    'setting_document_set' => 'Série de documentos',
    'setting_document_set_unavailable' => 'Séries indisponíveis (verifico a ligação)',
    'setting_tax_exemption' => 'Aplicar isenção de imposto',
    'setting_auto_create' => 'Criar automaticamente um documento quando pago uma fatura',
    'settings_product_mapping' => 'Predefinições de mapeamento de artigos',
    'settings_product_mapping_help' => 'IDs usados quando crio artigos no Moloni ON para as linhas da encomenda. Deixo 0 para omitir. Os impostos são obtidos automaticamente da taxa de IVA de cada encomenda.',
    'setting_measurement_unit' => 'ID da unidade de medida',
    'setting_product_category' => 'ID da categoria de artigo',
    'setting_exemption_reason' => 'Código de motivo de isenção',
    'setting_exemption_reason_help' => 'Código de motivo de isenção do Moloni aplicado às linhas com taxa de 0% (ex.: M07).',
    'settings_customer_mapping' => 'Cliente e zona fiscal',
    'setting_fiscal_zone_based_on' => 'Zona fiscal baseada em',
    'setting_fiscal_zone_based_on_help' => 'De onde vem a zona fiscal do documento. A faturação recorre à zona da empresa quando o cliente não tem país.',
    'fiscal_zone_company' => 'Zona fiscal da empresa',
    'fiscal_zone_billing' => 'País de faturação do cliente',
    'setting_vat_field' => 'Campo personalizado de NIF',
    'setting_vat_field_help' => 'Nome do campo personalizado de cliente WHMCS com o NIF. Deixo vazio para usar o NIF do cliente.',
    'save_settings' => 'Guardar configurações',
    'settings_saved' => 'Guardei as configurações.',

    // Ferramentas
    'tools_title' => 'Ferramentas',
    'tools_connection' => 'Ligação',
    'tools_connected' => 'Estou ligado a :company.',
    'tools_not_connected' => 'Não estou ligado a nenhuma empresa.',
    'tools_more' => 'Mais ferramentas',
    'tools_coming_soon' => 'Vou disponibilizar mais utilitários em breve.',

    // Registos
    'logs_title' => 'Os meus registos',
    'logs_all_levels' => 'Todos os níveis',
    'logs_empty' => 'Não tenho registos.',
    'clear_logs' => 'Limpar todos os registos',
    'confirm_clear_logs' => 'Elimino todos os registos? Não posso anular esta ação.',
    'logs_cleared' => 'Limpei os registos.',
    'view_context' => 'Ver',
    'log_context_title' => 'Contexto do registo',
    'close' => 'Fechar',

    // OAuth / diversos
    'oauth_state_mismatch' => 'A autorização falhou: o token de segurança não corresponde. Volto a tentar ligar-me.',
    'redirecting' => 'A redirecionar para o Moloni ON…',
    'redirect_continue' => 'continuar',
    'pdf_download_failed' => 'Não consegui transferir o PDF.',

    // Resultados de operações
    'order_discarded' => 'Descartei a encomenda.',
    'order_reverted' => 'Repus a encomenda como pendente.',
    'document_created' => 'Criei o documento (ID :id).',
    'document_skipped' => 'Encomenda ignorada: fatura de pagamento em massa, nada a faturar.',
    'document_failed' => 'Não consegui criar o documento: :error',
    'no_orders_selected' => 'Não selecionei nenhuma encomenda.',
    'bulk_result' => 'Criei :created documento(s); :skipped ignoradas; :failed falharam.',
];
