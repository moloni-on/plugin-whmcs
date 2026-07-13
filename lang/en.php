<?php

/**
 * English translations for the Moloni ON WHMCS module.
 *
 * @return array<string,string>
 */

declare(strict_types=1);

return [
    // Layout / navigation
    'module_name' => 'Moloni ON',
    'current_company' => 'Company',
    'nav_orders' => 'Orders',
    'nav_documents' => 'Documents',
    'nav_discarded' => 'Discarded',
    'nav_settings' => 'Settings',
    'nav_tools' => 'Tools',
    'nav_logs' => 'Logs',
    'nav_logout' => 'Log out',
    'footer_help_lead' => 'Need help?',
    'footer_help_link' => 'Check out our guides.',

    // Login
    'login_title' => 'Connect to Moloni ON',
    'login_intro' => 'Enter your Moloni ON API credentials to authorize this WHMCS installation.',
    'developer_id' => 'API Client ID',
    'client_secret' => 'Client secret',
    'connect' => 'Connect',
    'credentials_required' => 'Please provide both the API client ID and secret.',

    // Company selection
    'company_title' => 'Select a company',
    'company_none' => 'No companies are available for this account.',
    'select_company' => 'Select company',

    // Orders
    'orders_title' => 'Pending orders',
    'orders_empty' => 'There are no orders pending synchronization.',
    'create_selected' => 'Create documents for selected',
    'create' => 'Create',
    'discard' => 'Discard',
    'confirm_discard' => 'Discard this order? It will no longer be synced.',
    'confirm_bulk' => 'Create documents for all selected orders?',
    'status_pending' => 'Pending',
    'status_failed' => 'Failed',

    // Documents
    'documents_title' => 'Created documents',
    'documents_empty' => 'No documents have been created yet.',
    'download_pdf' => 'Download PDF',
    'view_in_moloni' => 'View in Moloni ON',
    'discarded_title' => 'Discarded orders',
    'discarded_empty' => 'No discarded orders.',
    'revert' => 'Move to pending',

    // Table columns
    'col_order' => 'Order',
    'col_customer' => 'Customer',
    'col_amount' => 'Amount',
    'col_date' => 'Date',
    'col_status' => 'Status',
    'col_actions' => 'Actions',
    'col_document' => 'Document #',
    'col_total' => 'Total',
    'col_timestamp' => 'Timestamp',
    'col_level' => 'Level',
    'col_message' => 'Message',
    'col_context' => 'Context',

    // Document types
    'doctype_invoice' => 'Invoice',
    'doctype_invoiceReceipt' => 'Invoice-Receipt',
    'doctype_simplifiedInvoice' => 'Simplified Invoice',
    'doctype_proFormaInvoice' => 'Pro Forma Invoice',
    'doctype_purchaseOrder' => 'Purchase Order',
    'doctype_estimate' => 'Estimate',

    // Settings
    'settings_title' => 'Settings',
    'settings_document_defaults' => 'Document defaults',
    'settings_document_defaults_help' => 'What kind of document to create in Moloni ON and how it is issued.',
    'settings_automation' => 'Automation',
    'settings_automation_help' => 'When documents are created automatically and what happens after.',
    'setting_document_type' => 'Default document type',
    'setting_document_status' => 'Document status',
    'status_draft' => 'Draft',
    'status_closed' => 'Closed',
    'setting_document_set' => 'Document set',
    'setting_document_set_unavailable' => 'Document sets unavailable (check connection)',
    'document_sets_unavailable' => 'Could not load document sets from Moloni ON. Make sure your company has a document set (série) defined in Moloni ON, then reload this page.',
    'setting_auto_create' => 'Automatically create a document when an invoice is paid',
    'setting_payment_method' => 'Default payment method',
    'setting_payment_method_help' => 'Used when the order\'s payment gateway can\'t be matched to a Moloni ON payment method by name. Only added to document types that support payments.',
    'setting_send_email' => 'E-mail the document to the customer after creating it',
    'setting_send_email_help' => 'Only applies when the document status is Closed; drafts are never e-mailed.',
    'setting_option_none' => '— None —',
    'settings_product_mapping' => 'Product mapping defaults',
    'settings_product_mapping_help' => 'Defaults used when creating products in Moloni ON for order line items. Taxes are taken automatically from each order\'s VAT rate.',
    'setting_measurement_unit' => 'Measurement unit',
    'setting_product_category' => 'Product category',
    'setting_custom_reference' => 'Product reference custom field',
    'setting_custom_reference_help' => 'WHMCS product custom field whose description holds the Moloni reference for a hosting product. Leave as None to use the default reference.',
    'setting_exemption_reason' => 'Tax exemption reason',
    'setting_exemption_reason_help' => 'Exemption reason automatically applied to any line with no VAT (e.g. M07). Shown as a list when your fiscal zone defines reason codes, otherwise entered as free text.',
    'settings_customer_mapping' => 'Tax & fiscal zone',
    'setting_fiscal_zone_based_on' => 'Fiscal zone based on',
    'setting_fiscal_zone_based_on_help' => 'Where the document fiscal zone comes from. Billing falls back to the company zone when the client has no country.',
    'fiscal_zone_company' => 'Company fiscal zone',
    'fiscal_zone_billing' => 'Client billing country',
    'setting_vat_field' => 'VAT custom field',
    'setting_vat_field_help' => 'Name of the WHMCS client custom field holding the VAT number. Leave empty to use the client Tax ID.',
    'save_settings' => 'Save settings',
    'settings_saved' => 'Settings saved.',

    // Tools
    'tools_title' => 'Tools',
    'tools_connection' => 'Connection',
    'tools_connected' => 'Connected to :company.',
    'tools_not_connected' => 'Not connected to a company.',
    'tools_more' => 'More tools',
    'tools_coming_soon' => 'Additional utilities are coming soon.',

    // Logs
    'logs_title' => 'Logs',
    'logs_all_levels' => 'All levels',
    'logs_empty' => 'No log entries.',
    'clear_logs' => 'Clear old logs',
    'confirm_clear_logs' => 'Delete log entries older than a week? This cannot be undone.',
    'logs_cleared' => 'Logs cleared.',
    'view_context' => 'View',
    'log_context_title' => 'Log context',
    'close' => 'Close',

    // Pagination
    'pagination_label' => 'Pagination',
    'pagination_prev' => 'Previous',
    'pagination_next' => 'Next',
    'pagination_summary' => 'Showing :from–:to of :total',

    // OAuth / misc
    'oauth_state_mismatch' => 'Authorization failed: the security token did not match. Please try connecting again.',
    'redirecting' => 'Redirecting to Moloni ON…',
    'redirect_continue' => 'continue',
    'pdf_download_failed' => 'Could not download PDF.',
    'open_document_failed' => 'Could not open the document in Moloni ON.',

    // Operation results
    'order_discarded' => 'Order discarded.',
    'order_reverted' => 'Order moved back to pending.',
    'document_created' => 'Document created (ID :id).',
    'document_skipped' => 'Order skipped: mass-payment invoice, nothing to bill.',
    'document_failed' => 'Document creation failed: :error',
    'no_orders_selected' => 'No orders were selected.',
    'bulk_result' => ':created document(s) created, :skipped skipped, :failed failed.',
];
