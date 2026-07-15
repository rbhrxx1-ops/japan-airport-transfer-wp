#!/usr/bin/env bash
set -u

PRIMARY_HOST="meetandgreet.jp"
PRIMARY_ORIGIN="https://${PRIMARY_HOST}"
WWW_HOST="www.${PRIMARY_HOST}"
LEGACY_HOST="japanairporttransfer.com"
CHECK_PATH="/domain-migration-check/"
FAILURES=0

pass() {
  printf 'PASS: %s\n' "$1"
}

fail() {
  printf 'FAIL: %s\n' "$1" >&2
  FAILURES=$((FAILURES + 1))
}

fetch() {
  curl --silent --show-error --fail --location --max-time 20 "$1"
}

certificate_sans() {
  local host="$1"
  timeout 20 openssl s_client -connect "${host}:443" -servername "$host" </dev/null 2>/dev/null \
    | openssl x509 -noout -ext subjectAltName 2>/dev/null
}

printf '只读检查：不会登录后台、提交表单或修改生产数据。\n'

home_html="$(fetch "${PRIMARY_ORIGIN}/" 2>/dev/null || true)"
if [[ -n "$home_html" ]]; then
  pass "新主域首页可通过 HTTPS 访问"
else
  fail "新主域首页无法通过 HTTPS 访问"
fi

if grep -Eqi '<link[^>]+rel="canonical"[^>]+href="https://meetandgreet\.jp/?"|<link[^>]+href="https://meetandgreet\.jp/?"[^>]+rel="canonical"' <<<"$home_html"; then
  pass "首页 canonical 指向 https://meetandgreet.jp/"
else
  fail "首页 canonical 未准确指向 https://meetandgreet.jp/"
fi

rest_json="$(fetch "${PRIMARY_ORIGIN}/wp-json/" 2>/dev/null || true)"
if grep -Fq '"url":"https:\/\/meetandgreet.jp"' <<<"$rest_json" \
  && grep -Fq '"home":"https:\/\/meetandgreet.jp"' <<<"$rest_json"; then
  pass "WordPress url/home 均为新主域"
else
  fail "WordPress REST 根信息中的 url/home 不是新主域"
fi

root_sans="$(certificate_sans "$PRIMARY_HOST" || true)"
if grep -Fq "DNS:${PRIMARY_HOST}" <<<"$root_sans"; then
  pass "根域 TLS 证书覆盖 ${PRIMARY_HOST}"
else
  fail "根域 TLS 证书未覆盖 ${PRIMARY_HOST}"
fi

www_sans="$(certificate_sans "$WWW_HOST" || true)"
if grep -Fq "DNS:${WWW_HOST}" <<<"$www_sans"; then
  pass "www TLS 证书覆盖 ${WWW_HOST}"
else
  fail "www TLS 证书未覆盖 ${WWW_HOST}"
fi

legacy_headers="$(curl --silent --show-error --head --max-time 20 "https://${LEGACY_HOST}${CHECK_PATH}" 2>/dev/null || true)"
legacy_status="$(awk 'toupper($1) ~ /^HTTP\// { status=$2 } END { print status }' <<<"$legacy_headers")"
legacy_location="$(awk 'BEGIN { IGNORECASE=1 } /^location:/ { sub(/\r$/, ""); print $2; exit }' <<<"$legacy_headers")"
if [[ "$legacy_status" =~ ^30[12378]$ ]] && [[ "$legacy_location" == "${PRIMARY_ORIGIN}${CHECK_PATH}"* ]]; then
  pass "旧域名按原路径重定向到新主域"
else
  fail "旧域名未按原路径 301/302/307/308 到新主域（status=${legacy_status:-不可达}, location=${legacy_location:-无}）"
fi

if git grep -n -I 'japanairporttransfer\.com' -- wp-content bin ':!tests/domain-migration-readonly.sh' >/tmp/jat-domain-code-leaks.txt; then
  cat /tmp/jat-domain-code-leaks.txt >&2
  fail "运行时代码仍包含旧域名硬编码"
else
  pass "运行时代码不存在旧域名硬编码"
fi
rm -f /tmp/jat-domain-code-leaks.txt

if (( FAILURES > 0 )); then
  printf 'DOMAIN_MIGRATION_GATE=FAIL (%d)\n' "$FAILURES" >&2
  exit 1
fi

printf 'DOMAIN_MIGRATION_GATE=PASS\n'
