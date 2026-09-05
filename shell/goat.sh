#!/usr/bin/env zsh
# GOAT — shell startup banner
# Source this from ~/.zshrc or ~/.bashrc to show meeeh.png + intro on terminal open.
#
#   echo 'source /path/to/project/shell/goat.sh' >> ~/.zshrc
# or after publishing:
#   php artisan vendor:publish --tag=goat-shell
#   source ./shell/goat.sh
#
# It will:
#   - cd to your Laravel project root (auto-detected or override GOAT_PROJECT_DIR)
#   - run `php artisan goat --compact` with meeeh.png left + intro right
#   - silently skip if not in a GOAT project or if GOAT_NO_BANNER=1

# Allow opt-out
if [[ "${GOAT_NO_BANNER}" == "1" || "${GOAT_NO_BANNER}" == "true" ]]; then
  return 0 2>/dev/null || exit 0
fi

# Only run in interactive shells
if [[ $- != *i* ]]; then
  return 0 2>/dev/null || exit 0
fi

# Resolve project dir
if [[ -z "${GOAT_PROJECT_DIR}" ]]; then
  # Try to find artisan up the tree from this script
  _goat_script_dir="${0:A:h}" 2>/dev/null || _goat_script_dir="$(cd "$(dirname "$0")" && pwd)"
  if [[ -f "${_goat_script_dir}/../artisan" ]]; then
    GOAT_PROJECT_DIR="$(cd "${_goat_script_dir}/.." && pwd)"
  elif [[ -f "${PWD}/artisan" ]]; then
    GOAT_PROJECT_DIR="${PWD}"
  else
    # Fallback: try to locate via git root
    _goat_git_root="$(git rev-parse --show-toplevel 2>/dev/null)"
    if [[ -n "${_goat_git_root}" && -f "${_goat_git_root}/artisan" ]]; then
      GOAT_PROJECT_DIR="${_goat_git_root}"
    fi
  fi
fi

# Only render if artisan exists and goat command is available
if [[ -n "${GOAT_PROJECT_DIR}" && -f "${GOAT_PROJECT_DIR}/artisan" ]]; then
  # Check GOAT is installed without spamming (quiet)
  if grep -q "laravel-goat" "${GOAT_PROJECT_DIR}/composer.json" 2>/dev/null; then
    # Use compact banner, 1.5s timeout guard, respect width
    (cd "${GOAT_PROJECT_DIR}" && php artisan goat --compact 2>/dev/null) || true
  fi
fi

unset _goat_script_dir _goat_git_root
