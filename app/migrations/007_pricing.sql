-- Pricing strategy: what each service costs us (government and third-party fees), competitor prices found with sources, and price proposals awaiting the owner.
CREATE TABLE price_costs (
  id {{PK}}, service_slug VARCHAR(60) NOT NULL, variant VARCHAR(20) NOT NULL DEFAULT '', gov_fee_cents INTEGER NULL, third_party_cents INTEGER NULL,
  source_url VARCHAR(500) NULL, notes VARCHAR(500) NULL, status VARCHAR(20) NOT NULL DEFAULT 'unconfirmed', updated_by VARCHAR(190) NULL, updated_at DATETIME NOT NULL
){{ENGINE}};
CREATE UNIQUE INDEX ux_price_costs ON price_costs(service_slug, variant);
CREATE TABLE competitor_prices (
  id {{PK}}, service_slug VARCHAR(60) NOT NULL, variant VARCHAR(20) NOT NULL DEFAULT '', competitor VARCHAR(160) NOT NULL, region VARCHAR(60) NULL,
  price_cents INTEGER NOT NULL, includes_gov_fee INTEGER NULL, billing VARCHAR(20) NULL, url VARCHAR(500) NULL, notes VARCHAR(500) NULL,
  found_by VARCHAR(60) NOT NULL DEFAULT 'owner', status VARCHAR(20) NOT NULL DEFAULT 'active', observed_at DATETIME NOT NULL, created_at DATETIME NOT NULL
){{ENGINE}};
CREATE INDEX ix_competitor_service ON competitor_prices(service_slug, variant);
CREATE TABLE price_proposals (
  id {{PK}}, service_slug VARCHAR(60) NOT NULL, variant VARCHAR(20) NOT NULL DEFAULT '', current_cents INTEGER NULL, proposed_cents INTEGER NOT NULL,
  strategy VARCHAR(40) NOT NULL DEFAULT 'match', rationale {{TEXT}}, status VARCHAR(20) NOT NULL DEFAULT 'open', created_by VARCHAR(60) NOT NULL,
  decided_by VARCHAR(190) NULL, created_at DATETIME NOT NULL, decided_at DATETIME NULL
){{ENGINE}};
