-- 818 Digital Agency — core schema. Tokens are expanded per driver by App\Db::migrate().
-- {{PK}} primary key, {{FK}} foreign-key integer, {{TEXT}} long text, {{ENGINE}} table options.

CREATE TABLE users (
  id {{PK}}, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, name VARCHAR(190) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'customer', status VARCHAR(20) NOT NULL DEFAULT 'active',
  mfa_secret_enc {{TEXT}}, mfa_enabled INTEGER NOT NULL DEFAULT 0, failed_logins INTEGER NOT NULL DEFAULT 0,
  locked_until DATETIME NULL, last_login_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE role_permissions (
  id {{PK}}, role VARCHAR(20) NOT NULL, permission VARCHAR(60) NOT NULL, UNIQUE(role, permission)
){{ENGINE}};

CREATE TABLE password_resets (
  id {{PK}}, user_id {{FK}} NOT NULL REFERENCES users(id) ON DELETE CASCADE, token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL, used_at DATETIME NULL, created_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE customers (
  id {{PK}}, user_id {{FK}} NULL REFERENCES users(id) ON DELETE SET NULL, name VARCHAR(190) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL, phone VARCHAR(40) NULL, business_name VARCHAR(190) NULL, industry VARCHAR(120) NULL,
  location VARCHAR(190) NULL, province CHAR(3) NULL, lead_source VARCHAR(60) NULL, customer_type VARCHAR(30) NOT NULL DEFAULT 'individual',
  status VARCHAR(30) NOT NULL DEFAULT 'visitor', interests {{TEXT}}, tags {{TEXT}}, lifetime_value_cents INTEGER NOT NULL DEFAULT 0,
  consent_marketing INTEGER NOT NULL DEFAULT 0, consent_at DATETIME NULL, memory_opt_in INTEGER NOT NULL DEFAULT 1,
  notes {{TEXT}}, last_contact_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE(email)
){{ENGINE}};
CREATE INDEX ix_customers_status ON customers(status);

CREATE TABLE leads (
  id {{PK}}, customer_id {{FK}} NOT NULL REFERENCES customers(id) ON DELETE CASCADE, source VARCHAR(60) NOT NULL DEFAULT 'website',
  intent VARCHAR(120) NULL, summary {{TEXT}}, services_interest {{TEXT}}, budget VARCHAR(60) NULL, timeline VARCHAR(60) NULL,
  score INTEGER NOT NULL DEFAULT 0, urgency VARCHAR(20) NOT NULL DEFAULT 'normal', status VARCHAR(30) NOT NULL DEFAULT 'lead',
  estimated_value_cents INTEGER NOT NULL DEFAULT 0, next_follow_up_at DATETIME NULL, follow_up_count INTEGER NOT NULL DEFAULT 0,
  opted_out INTEGER NOT NULL DEFAULT 0, conversation_id {{FK}} NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
){{ENGINE}};
CREATE INDEX ix_leads_status ON leads(status);
CREATE INDEX ix_leads_followup ON leads(next_follow_up_at);

CREATE TABLE conversations (
  id {{PK}}, public_id CHAR(32) NOT NULL UNIQUE, customer_id {{FK}} NULL REFERENCES customers(id) ON DELETE SET NULL,
  user_id {{FK}} NULL REFERENCES users(id) ON DELETE SET NULL, channel VARCHAR(30) NOT NULL DEFAULT 'web',
  agent_slug VARCHAR(60) NOT NULL DEFAULT 'concierge', page_context VARCHAR(190) NULL, status VARCHAR(20) NOT NULL DEFAULT 'open',
  handoff_requested INTEGER NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE messages (
  id {{PK}}, conversation_id {{FK}} NOT NULL REFERENCES conversations(id) ON DELETE CASCADE, role VARCHAR(20) NOT NULL,
  content {{TEXT}} NOT NULL, meta {{TEXT}}, created_at DATETIME NOT NULL
){{ENGINE}};
CREATE INDEX ix_messages_conv ON messages(conversation_id);

CREATE TABLE services (
  id {{PK}}, slug VARCHAR(60) NOT NULL UNIQUE, category VARCHAR(40) NOT NULL, name VARCHAR(190) NOT NULL, summary VARCHAR(255) NOT NULL DEFAULT '',
  description {{TEXT}}, benefits {{TEXT}}, deliverables {{TEXT}}, pricing_model VARCHAR(20) NOT NULL DEFAULT 'one_time',
  price_cents INTEGER NULL, setup_cents INTEGER NOT NULL DEFAULT 0, province_prices {{TEXT}}, options {{TEXT}}, timeline VARCHAR(80) NULL,
  requirements {{TEXT}}, eligibility {{TEXT}}, limitations {{TEXT}}, faqs {{TEXT}}, terms {{TEXT}}, addons {{TEXT}},
  ai_capability VARCHAR(30) NOT NULL DEFAULT 'assist', human_required {{TEXT}}, delivery_agent VARCHAR(60) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active', sort INTEGER NOT NULL DEFAULT 100, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE packages (
  id {{PK}}, slug VARCHAR(60) NOT NULL UNIQUE, name VARCHAR(190) NOT NULL, description {{TEXT}}, discount_pct INTEGER NOT NULL DEFAULT 0,
  items {{TEXT}} NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'active', created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE coupons (
  id {{PK}}, code VARCHAR(40) NOT NULL UNIQUE, type VARCHAR(10) NOT NULL, value_amount INTEGER NOT NULL, applies_to {{TEXT}},
  active INTEGER NOT NULL DEFAULT 1, expires_at DATETIME NULL, uses INTEGER NOT NULL DEFAULT 0, max_uses INTEGER NULL,
  created_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE proposals (
  id {{PK}}, number VARCHAR(30) NOT NULL, version INTEGER NOT NULL DEFAULT 1, customer_id {{FK}} NULL REFERENCES customers(id) ON DELETE SET NULL,
  lead_id {{FK}} NULL REFERENCES leads(id) ON DELETE SET NULL, status VARCHAR(20) NOT NULL DEFAULT 'draft', title VARCHAR(190) NOT NULL,
  scope {{TEXT}}, items {{TEXT}} NOT NULL, one_time_cents INTEGER NOT NULL DEFAULT 0, recurring_cents INTEGER NOT NULL DEFAULT 0,
  discount_cents INTEGER NOT NULL DEFAULT 0, timeline VARCHAR(120) NULL, assumptions {{TEXT}}, exclusions {{TEXT}}, terms {{TEXT}},
  needs_quote INTEGER NOT NULL DEFAULT 0, expires_at DATETIME NULL, sent_at DATETIME NULL, accepted_at DATETIME NULL,
  public_token CHAR(40) NOT NULL UNIQUE, created_by VARCHAR(60) NOT NULL DEFAULT 'system', created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
  UNIQUE(number, version)
){{ENGINE}};

CREATE TABLE orders (
  id {{PK}}, number VARCHAR(30) NOT NULL UNIQUE, customer_id {{FK}} NOT NULL REFERENCES customers(id), user_id {{FK}} NULL REFERENCES users(id) ON DELETE SET NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending', subtotal_cents INTEGER NOT NULL, discount_cents INTEGER NOT NULL DEFAULT 0,
  tax_cents INTEGER NOT NULL DEFAULT 0, total_cents INTEGER NOT NULL, recurring_cents INTEGER NOT NULL DEFAULT 0, currency CHAR(3) NOT NULL DEFAULT 'CAD',
  coupon_code VARCHAR(40) NULL, province CHAR(3) NULL, stripe_session_id VARCHAR(120) NULL UNIQUE, stripe_payment_intent VARCHAR(120) NULL,
  proposal_id {{FK}} NULL, meta {{TEXT}}, paid_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
){{ENGINE}};
CREATE INDEX ix_orders_customer ON orders(customer_id);
CREATE INDEX ix_orders_status ON orders(status);

CREATE TABLE order_items (
  id {{PK}}, order_id {{FK}} NOT NULL REFERENCES orders(id) ON DELETE CASCADE, service_slug VARCHAR(60) NOT NULL, name VARCHAR(190) NOT NULL,
  qty INTEGER NOT NULL DEFAULT 1, unit_cents INTEGER NOT NULL, setup_cents INTEGER NOT NULL DEFAULT 0, billing VARCHAR(20) NOT NULL DEFAULT 'one_time', options {{TEXT}}
){{ENGINE}};

CREATE TABLE payments (
  id {{PK}}, order_id {{FK}} NULL REFERENCES orders(id) ON DELETE SET NULL, provider VARCHAR(30) NOT NULL, provider_ref VARCHAR(120) NOT NULL UNIQUE,
  amount_cents INTEGER NOT NULL, status VARCHAR(30) NOT NULL, kind VARCHAR(20) NOT NULL DEFAULT 'charge', created_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE subscriptions (
  id {{PK}}, customer_id {{FK}} NOT NULL REFERENCES customers(id), order_id {{FK}} NULL REFERENCES orders(id) ON DELETE SET NULL,
  provider_ref VARCHAR(120) NULL UNIQUE, status VARCHAR(30) NOT NULL, amount_cents INTEGER NOT NULL DEFAULT 0, current_period_end DATETIME NULL,
  created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE invoices (
  id {{PK}}, number VARCHAR(30) NOT NULL UNIQUE, order_id {{FK}} NULL REFERENCES orders(id) ON DELETE SET NULL, customer_id {{FK}} NOT NULL REFERENCES customers(id),
  amount_cents INTEGER NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'issued', due_at DATETIME NULL, issued_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE webhook_events (
  id {{PK}}, provider VARCHAR(30) NOT NULL, event_id VARCHAR(120) NOT NULL, type VARCHAR(80) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'received',
  error {{TEXT}}, received_at DATETIME NOT NULL, processed_at DATETIME NULL, UNIQUE(provider, event_id)
){{ENGINE}};

CREATE TABLE projects (
  id {{PK}}, number VARCHAR(30) NOT NULL UNIQUE, customer_id {{FK}} NOT NULL REFERENCES customers(id), order_id {{FK}} NULL REFERENCES orders(id) ON DELETE SET NULL,
  service_slug VARCHAR(60) NOT NULL, title VARCHAR(190) NOT NULL, brief {{TEXT}}, requirements {{TEXT}}, status VARCHAR(30) NOT NULL DEFAULT 'awaiting_requirements',
  progress INTEGER NOT NULL DEFAULT 0, deadline DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
){{ENGINE}};
CREATE INDEX ix_projects_customer ON projects(customer_id);

CREATE TABLE project_milestones (
  id {{PK}}, project_id {{FK}} NOT NULL REFERENCES projects(id) ON DELETE CASCADE, title VARCHAR(190) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending',
  sort INTEGER NOT NULL DEFAULT 0, agent_slug VARCHAR(60) NULL, due_at DATETIME NULL, completed_at DATETIME NULL
){{ENGINE}};

CREATE TABLE project_activity (
  id {{PK}}, project_id {{FK}} NOT NULL REFERENCES projects(id) ON DELETE CASCADE, kind VARCHAR(40) NOT NULL, summary VARCHAR(500) NOT NULL,
  visible_to_customer INTEGER NOT NULL DEFAULT 1, actor VARCHAR(60) NOT NULL, created_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE documents (
  id {{PK}}, customer_id {{FK}} NULL REFERENCES customers(id) ON DELETE CASCADE, project_id {{FK}} NULL REFERENCES projects(id) ON DELETE SET NULL,
  order_id {{FK}} NULL, kind VARCHAR(30) NOT NULL DEFAULT 'file', filename VARCHAR(190) NOT NULL, stored_name VARCHAR(80) NOT NULL UNIQUE,
  mime VARCHAR(100) NOT NULL, size_bytes INTEGER NOT NULL, sha256 CHAR(64) NOT NULL, uploaded_by VARCHAR(60) NOT NULL, created_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE deliverables (
  id {{PK}}, project_id {{FK}} NOT NULL REFERENCES projects(id) ON DELETE CASCADE, title VARCHAR(190) NOT NULL, body {{TEXT}},
  document_id {{FK}} NULL REFERENCES documents(id) ON DELETE SET NULL, status VARCHAR(20) NOT NULL DEFAULT 'draft', qa_status VARCHAR(20) NOT NULL DEFAULT 'pending',
  qa_notes {{TEXT}}, produced_by VARCHAR(60) NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE tickets (
  id {{PK}}, number VARCHAR(30) NOT NULL UNIQUE, customer_id {{FK}} NULL REFERENCES customers(id) ON DELETE SET NULL, category VARCHAR(30) NOT NULL DEFAULT 'general',
  priority VARCHAR(10) NOT NULL DEFAULT 'normal', status VARCHAR(20) NOT NULL DEFAULT 'open', subject VARCHAR(190) NOT NULL, sentiment VARCHAR(20) NULL,
  assigned_to VARCHAR(60) NULL, csat INTEGER NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, resolved_at DATETIME NULL
){{ENGINE}};

CREATE TABLE ticket_messages (
  id {{PK}}, ticket_id {{FK}} NOT NULL REFERENCES tickets(id) ON DELETE CASCADE, author_type VARCHAR(20) NOT NULL, author_name VARCHAR(120) NOT NULL,
  body {{TEXT}} NOT NULL, internal INTEGER NOT NULL DEFAULT 0, created_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE reviews (
  id {{PK}}, customer_id {{FK}} NOT NULL REFERENCES customers(id) ON DELETE CASCADE, project_id {{FK}} NULL REFERENCES projects(id) ON DELETE SET NULL,
  rating INTEGER NOT NULL, body {{TEXT}}, consent_publish INTEGER NOT NULL DEFAULT 0, verified INTEGER NOT NULL DEFAULT 0,
  published INTEGER NOT NULL DEFAULT 0, display_name VARCHAR(120) NULL, created_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE notifications (
  id {{PK}}, user_id {{FK}} NOT NULL REFERENCES users(id) ON DELETE CASCADE, title VARCHAR(190) NOT NULL, body VARCHAR(500) NULL,
  link VARCHAR(255) NULL, read_at DATETIME NULL, created_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE email_log (
  id {{PK}}, dedupe_key VARCHAR(120) NULL UNIQUE, to_email VARCHAR(190) NOT NULL, subject VARCHAR(255) NOT NULL, template VARCHAR(60) NOT NULL,
  provider VARCHAR(20) NULL, provider_id VARCHAR(120) NULL, status VARCHAR(20) NOT NULL DEFAULT 'queued', error {{TEXT}},
  related_type VARCHAR(30) NULL, related_id INTEGER NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
){{ENGINE}};
CREATE INDEX ix_email_provider ON email_log(provider_id);

CREATE TABLE email_suppressions (
  id {{PK}}, email VARCHAR(190) NOT NULL UNIQUE, reason VARCHAR(40) NOT NULL, created_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE integrations (
  id {{PK}}, slug VARCHAR(40) NOT NULL UNIQUE, category VARCHAR(30) NOT NULL, name VARCHAR(80) NOT NULL, enabled INTEGER NOT NULL DEFAULT 0,
  secrets_enc {{TEXT}}, settings {{TEXT}}, status VARCHAR(20) NOT NULL DEFAULT 'not_configured', last_ok_at DATETIME NULL, last_error {{TEXT}},
  updated_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE integration_logs (
  id {{PK}}, slug VARCHAR(40) NOT NULL, action VARCHAR(60) NOT NULL, ok INTEGER NOT NULL, detail VARCHAR(500) NULL,
  correlation_id VARCHAR(40) NULL, created_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE agents (
  id {{PK}}, slug VARCHAR(60) NOT NULL UNIQUE, name VARCHAR(120) NOT NULL, role {{TEXT}} NOT NULL, instructions {{TEXT}} NOT NULL,
  tools {{TEXT}} NOT NULL, permissions {{TEXT}} NOT NULL, model VARCHAR(80) NOT NULL, fallback_model VARCHAR(80) NULL,
  temperature DECIMAL(3,2) NOT NULL DEFAULT 0.30, max_tokens INTEGER NOT NULL DEFAULT 1200, autonomy_level INTEGER NOT NULL DEFAULT 2,
  status VARCHAR(20) NOT NULL DEFAULT 'enabled', daily_budget_cents INTEGER NOT NULL DEFAULT 200, monthly_budget_cents INTEGER NOT NULL DEFAULT 3000,
  task_budget_cents INTEGER NOT NULL DEFAULT 25, max_iterations INTEGER NOT NULL DEFAULT 6, timeout_seconds INTEGER NOT NULL DEFAULT 90,
  max_retries INTEGER NOT NULL DEFAULT 3, escalation {{TEXT}}, memory_policy VARCHAR(40) NOT NULL DEFAULT 'task',
  input_schema {{TEXT}}, output_schema {{TEXT}}, version INTEGER NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE agent_versions (
  id {{PK}}, agent_id {{FK}} NOT NULL REFERENCES agents(id) ON DELETE CASCADE, version INTEGER NOT NULL, snapshot {{TEXT}} NOT NULL,
  changed_by VARCHAR(120) NOT NULL, created_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE agent_tasks (
  id {{PK}}, public_id CHAR(16) NOT NULL UNIQUE, agent_slug VARCHAR(60) NOT NULL, parent_id {{FK}} NULL, workflow_run_id {{FK}} NULL,
  kind VARCHAR(60) NOT NULL, title VARCHAR(190) NOT NULL, input {{TEXT}}, output {{TEXT}}, progress_note VARCHAR(255) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'queued', priority INTEGER NOT NULL DEFAULT 5, attempts INTEGER NOT NULL DEFAULT 0, max_attempts INTEGER NOT NULL DEFAULT 3,
  run_after DATETIME NOT NULL, locked_until DATETIME NULL, locked_by VARCHAR(40) NULL, deadline DATETIME NULL, error {{TEXT}},
  customer_id {{FK}} NULL, project_id {{FK}} NULL, approval_id {{FK}} NULL, cost_micros INTEGER NOT NULL DEFAULT 0,
  tokens_in INTEGER NOT NULL DEFAULT 0, tokens_out INTEGER NOT NULL DEFAULT 0, dedupe_key VARCHAR(120) NULL UNIQUE,
  created_by VARCHAR(60) NOT NULL DEFAULT 'system', created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, started_at DATETIME NULL, finished_at DATETIME NULL
){{ENGINE}};
CREATE INDEX ix_tasks_queue ON agent_tasks(status, run_after, priority);
CREATE INDEX ix_tasks_agent ON agent_tasks(agent_slug, status);

CREATE TABLE agent_runs (
  id {{PK}}, task_id {{FK}} NULL, agent_slug VARCHAR(60) NOT NULL, provider VARCHAR(30) NOT NULL, model VARCHAR(80) NOT NULL, status VARCHAR(20) NOT NULL,
  iterations INTEGER NOT NULL DEFAULT 0, tokens_in INTEGER NOT NULL DEFAULT 0, tokens_out INTEGER NOT NULL DEFAULT 0, cost_micros INTEGER NOT NULL DEFAULT 0,
  duration_ms INTEGER NOT NULL DEFAULT 0, tools_used {{TEXT}}, summary VARCHAR(500) NULL, error {{TEXT}}, created_at DATETIME NOT NULL
){{ENGINE}};
CREATE INDEX ix_runs_agent ON agent_runs(agent_slug, created_at);

CREATE TABLE ai_usage (
  id {{PK}}, agent_slug VARCHAR(60) NOT NULL, task_id {{FK}} NULL, conversation_id {{FK}} NULL, provider VARCHAR(30) NOT NULL, model VARCHAR(80) NOT NULL,
  tokens_in INTEGER NOT NULL, tokens_out INTEGER NOT NULL, cost_micros INTEGER NOT NULL, created_at DATETIME NOT NULL
){{ENGINE}};
CREATE INDEX ix_usage_day ON ai_usage(created_at);

CREATE TABLE approvals (
  id {{PK}}, kind VARCHAR(40) NOT NULL, title VARCHAR(190) NOT NULL, details {{TEXT}} NOT NULL, risk VARCHAR(10) NOT NULL DEFAULT 'medium',
  status VARCHAR(20) NOT NULL DEFAULT 'pending', requested_by VARCHAR(60) NOT NULL, subject_type VARCHAR(30) NULL, subject_id INTEGER NULL,
  payload {{TEXT}}, decided_by VARCHAR(120) NULL, decided_at DATETIME NULL, decision_note {{TEXT}}, created_at DATETIME NOT NULL
){{ENGINE}};
CREATE INDEX ix_approvals_status ON approvals(status);

CREATE TABLE events (
  id {{PK}}, name VARCHAR(60) NOT NULL, payload {{TEXT}}, status VARCHAR(20) NOT NULL DEFAULT 'pending', created_at DATETIME NOT NULL, processed_at DATETIME NULL
){{ENGINE}};
CREATE INDEX ix_events_status ON events(status);

CREATE TABLE workflows (
  id {{PK}}, slug VARCHAR(60) NOT NULL UNIQUE, name VARCHAR(120) NOT NULL, trigger_event VARCHAR(60) NOT NULL, definition {{TEXT}} NOT NULL,
  enabled INTEGER NOT NULL DEFAULT 1, version INTEGER NOT NULL DEFAULT 1, updated_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE workflow_runs (
  id {{PK}}, workflow_slug VARCHAR(60) NOT NULL, event_id {{FK}} NULL, status VARCHAR(20) NOT NULL DEFAULT 'running', context {{TEXT}},
  current_step INTEGER NOT NULL DEFAULT 0, run_after DATETIME NOT NULL, locked_until DATETIME NULL, attempts INTEGER NOT NULL DEFAULT 0,
  log {{TEXT}}, error {{TEXT}}, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE(workflow_slug, event_id)
){{ENGINE}};
CREATE INDEX ix_wfr_status ON workflow_runs(status, run_after);

CREATE TABLE kb_articles (
  id {{PK}}, slug VARCHAR(120) NOT NULL UNIQUE, title VARCHAR(190) NOT NULL, category VARCHAR(40) NOT NULL, body {{TEXT}} NOT NULL,
  tags VARCHAR(255) NULL, status VARCHAR(20) NOT NULL DEFAULT 'published', uses INTEGER NOT NULL DEFAULT 0, updated_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE memories (
  id {{PK}}, scope VARCHAR(20) NOT NULL, scope_id INTEGER NOT NULL DEFAULT 0, mkey VARCHAR(120) NOT NULL, mvalue {{TEXT}} NOT NULL,
  source VARCHAR(60) NOT NULL, expires_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE(scope, scope_id, mkey)
){{ENGINE}};

CREATE TABLE content_items (
  id {{PK}}, kind VARCHAR(30) NOT NULL, title VARCHAR(190) NOT NULL, slug VARCHAR(160) NOT NULL UNIQUE, body {{TEXT}}, excerpt VARCHAR(400) NULL,
  keyword VARCHAR(120) NULL, audience VARCHAR(120) NULL, meta {{TEXT}}, status VARCHAR(20) NOT NULL DEFAULT 'draft', seo_score INTEGER NULL,
  readability INTEGER NULL, publish_at DATETIME NULL, version INTEGER NOT NULL DEFAULT 1, author VARCHAR(60) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE content_versions (
  id {{PK}}, content_id {{FK}} NOT NULL REFERENCES content_items(id) ON DELETE CASCADE, version INTEGER NOT NULL, title VARCHAR(190) NOT NULL,
  body {{TEXT}}, author VARCHAR(60) NOT NULL, created_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE content_blocks (
  id {{PK}}, bkey VARCHAR(120) NOT NULL UNIQUE, value {{TEXT}} NOT NULL, updated_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE campaigns (
  id {{PK}}, name VARCHAR(190) NOT NULL, channel VARCHAR(40) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'draft', plan {{TEXT}},
  start_at DATETIME NULL, end_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE opportunities (
  id {{PK}}, name VARCHAR(190) NOT NULL, audience VARCHAR(190) NULL, problem {{TEXT}}, evidence {{TEXT}}, sources {{TEXT}}, monetization {{TEXT}},
  complexity VARCHAR(20) NULL, risks {{TEXT}}, confidence VARCHAR(10) NULL, score INTEGER NULL, recommendation {{TEXT}},
  status VARCHAR(20) NOT NULL DEFAULT 'new', created_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE expenses (
  id {{PK}}, category VARCHAR(40) NOT NULL, vendor VARCHAR(120) NOT NULL, amount_cents INTEGER NOT NULL, incurred_on DATE NOT NULL,
  note VARCHAR(255) NULL, created_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE audit_log (
  id {{PK}}, actor_type VARCHAR(20) NOT NULL, actor_id VARCHAR(60) NULL, action VARCHAR(80) NOT NULL, subject_type VARCHAR(30) NULL,
  subject_id VARCHAR(60) NULL, detail {{TEXT}}, ip VARCHAR(45) NULL, correlation_id VARCHAR(40) NULL, created_at DATETIME NOT NULL
){{ENGINE}};
CREATE INDEX ix_audit_created ON audit_log(created_at);

CREATE TABLE security_events (
  id {{PK}}, kind VARCHAR(40) NOT NULL, detail VARCHAR(500) NULL, ip VARCHAR(45) NULL, created_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE settings (
  id {{PK}}, skey VARCHAR(120) NOT NULL UNIQUE, value {{TEXT}}, updated_at DATETIME NOT NULL
){{ENGINE}};

CREATE TABLE rate_limits (
  id {{PK}}, rkey VARCHAR(190) NOT NULL UNIQUE, hits INTEGER NOT NULL, window_start INTEGER NOT NULL
){{ENGINE}};
