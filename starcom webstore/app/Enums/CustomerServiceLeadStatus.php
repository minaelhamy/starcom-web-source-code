<?php

namespace App\Enums;

interface CustomerServiceLeadStatus
{
    const NOT_APPROACHED = 'not_approached';
    const WAITING_DOCUMENTS = 'waiting_documents';
    const DOCUMENTS_RECEIVED = 'documents_received';
    const NOT_INTERESTED = 'not_interested';
    const VISIT_REQUIRED = 'visit_required';
    const NO_ANSWER = 'no_answer';
    const CONTACTED_WAITING_REPLY = 'contacted_waiting_reply';
    const CALL_LATER = 'call_later';
    const REJECTED_COMMERCIAL_REGISTER = 'rejected_commercial_register';
    const REVIEW_WITH_OWNER = 'review_with_owner';
    const NO_CREDIT_SALES = 'no_credit_sales';
    const NO_REGISTER_NO_ID_CONSENT = 'no_register_no_id_consent';
    const CLOSED = 'closed';
}
