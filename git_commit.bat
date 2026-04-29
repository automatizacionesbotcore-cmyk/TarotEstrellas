@echo off
cd /d c:\wamp64\www\TarotEstrella\tarotestrellas

echo ============================================================
echo STEP 1: git status
echo ============================================================
git status
echo.

echo ============================================================
echo STEP 2: git diff --stat
echo ============================================================
git diff --stat
echo.

echo ============================================================
echo STEP 3: git add -A
echo ============================================================
git add -A
echo.

echo ============================================================
echo STEP 4: git commit (with Co-authored-by trailer)
echo ============================================================
git commit -m "chore(backend): ajustes de avance de fase backend" -m "Commit de avances pendientes en la rama backend/feat/backend-avance-fase antes de iniciar trabajo en una nueva rama." -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
echo.

echo ============================================================
echo STEP 5: git log -1 --stat
echo ============================================================
git log -1 --stat
