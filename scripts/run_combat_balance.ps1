for ($i=1; $i -le 10; $i++) {
  Write-Host ("--- RUN " + $i + " ---")
  docker compose exec -T app php artisan test --filter CombatBalanceTest
}
