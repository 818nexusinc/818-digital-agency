-- Filing agent: update service notes for registration & management services
UPDATE services SET human_required = '["You sign the filing once in your portal. Our Filing Agent then submits it: automatically where the registry offers an API, through the official registry portal elsewhere."]' WHERE category IN ('registration', 'management') AND slug NOT IN ('nuans', 'bm_documents');
