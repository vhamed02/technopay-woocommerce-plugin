#!/bin/bash

set -Eeuo pipefail

PLUGIN_SLUG="technopay-payment-gateway-for-woocommerce"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
SOURCE_PATH="$SCRIPT_DIR/wp-content/plugins/$PLUGIN_SLUG"
MAIN_FILE="$SOURCE_PATH/technopay-woocommerce.php"
SVN_PATH="$SCRIPT_DIR/.svn/$PLUGIN_SLUG"
SVN_URL="https://plugins.svn.wordpress.org/$PLUGIN_SLUG"
OUTPUT_PATH="$HOME/Desktop"
SVN_MODE="ask"
SVN_MODE_SET="no"
COMMIT_MESSAGE=""
OUTPUT_SET="no"
TEMP_PATH=""

usage() {
    printf '%s\n' "Usage: ./release-plugin.sh [output-directory] [options]"
    printf '%s\n' ""
    printf '%s\n' "Options:"
    printf '%s\n' "  --output PATH       Write the ZIP file to PATH (default: \$HOME/Desktop)"
    printf '%s\n' "  --publish-svn       Publish trunk and create the version tag in WordPress SVN"
    printf '%s\n' "  --skip-svn          Skip WordPress SVN publication"
    printf '%s\n' "  --message TEXT      SVN trunk commit message"
    printf '%s\n' "  -h, --help          Show this help"
}

fail() {
    printf 'Error: %s\n' "$1" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "Required command not found: $1"
}

cleanup() {
    if [ -n "$TEMP_PATH" ] && [ -d "$TEMP_PATH" ]; then
        rm -rf "$TEMP_PATH"
    fi
}

read_value() {
    local option="$1"
    local value="${2:-}"

    [ -n "$value" ] || fail "$option requires a value"
    printf '%s' "$value"
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        --output)
            OUTPUT_PATH="$(read_value "$1" "${2:-}")"
            OUTPUT_SET="yes"
            shift 2
            ;;
        --publish-svn)
            [ "$SVN_MODE_SET" = "no" ] || fail "SVN mode was provided more than once"
            SVN_MODE="publish"
            SVN_MODE_SET="yes"
            shift
            ;;
        --skip-svn)
            [ "$SVN_MODE_SET" = "no" ] || fail "SVN mode was provided more than once"
            SVN_MODE="skip"
            SVN_MODE_SET="yes"
            shift
            ;;
        --message)
            COMMIT_MESSAGE="$(read_value "$1" "${2:-}")"
            shift 2
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        -*)
            fail "Unknown option: $1"
            ;;
        *)
            [ "$OUTPUT_SET" = "no" ] || fail "Output directory was provided more than once"
            OUTPUT_PATH="$1"
            OUTPUT_SET="yes"
            shift
            ;;
    esac
done

[ -f "$MAIN_FILE" ] || fail "Plugin source not found: $MAIN_FILE"

VERSION="$(sed -nE 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*([^[:space:]]+).*/\1/p' "$MAIN_FILE" | head -n 1)"
CODE_VERSION="$(awk -F "'" '/define\(.TPFW_VERSION./ { print $4; exit }' "$MAIN_FILE")"
STABLE_VERSION="$(sed -nE 's/^Stable tag:[[:space:]]*([^[:space:]]+).*/\1/p' "$SOURCE_PATH/readme.txt" | head -n 1)"

[ -n "$VERSION" ] || fail "Could not read the plugin version"
[ "$VERSION" = "$CODE_VERSION" ] || fail "Plugin header version and TPFW_VERSION do not match"
[ "$VERSION" = "$STABLE_VERSION" ] || fail "Plugin version and readme.txt Stable tag do not match"

if ! printf '%s' "$VERSION" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+([.-][A-Za-z0-9]+)*$'; then
    fail "Invalid plugin version: $VERSION"
fi

if [ "$SVN_MODE" = "ask" ]; then
    if [ -t 0 ]; then
        read -r -p "Publish version $VERSION to WordPress SVN? [y/N]: " SVN_CONFIRM
        case "$SVN_CONFIRM" in
            y|Y|yes|YES|Yes)
                SVN_MODE="publish"
                ;;
            *)
                SVN_MODE="skip"
                ;;
        esac
    else
        SVN_MODE="skip"
    fi
fi

publish_svn() {
    require_command svn
    require_command rsync

    if svn ls "$SVN_URL/tags/$VERSION" >/dev/null 2>&1; then
        fail "SVN tag $VERSION already exists. Bump the plugin version before publishing."
    fi

    mkdir -p "$(dirname "$SVN_PATH")"

    if [ -d "$SVN_PATH/.svn" ]; then
        svn update "$SVN_PATH"
    else
        svn checkout "$SVN_URL" "$SVN_PATH"
    fi

    rsync -a --delete \
        --exclude='.git' \
        --exclude='.svn' \
        --exclude='.DS_Store' \
        --exclude='node_modules' \
        --exclude='.gitignore' \
        --exclude='*.log' \
        "$SOURCE_PATH/" "$SVN_PATH/trunk/"

    svn add --force "$SVN_PATH/trunk" >/dev/null

    while IFS= read -r status_line; do
        if [ "${status_line:0:1}" = "!" ]; then
            svn rm -- "${status_line:8}"
        fi
    done < <(svn status "$SVN_PATH/trunk")

    if [ -z "$COMMIT_MESSAGE" ]; then
        if [ -t 0 ]; then
            read -r -p "SVN commit message [Release version $VERSION]: " COMMIT_MESSAGE
        fi

        if [ -z "$COMMIT_MESSAGE" ]; then
            COMMIT_MESSAGE="Release version $VERSION"
        fi
    fi

    if [ -n "$(svn status "$SVN_PATH/trunk")" ]; then
        svn commit "$SVN_PATH/trunk" -m "$COMMIT_MESSAGE"
    else
        printf 'SVN trunk already matches the plugin source.\n'
    fi

    svn copy "$SVN_URL/trunk" "$SVN_URL/tags/$VERSION" -m "Tagging version $VERSION"
    svn update "$SVN_PATH"
    printf 'Published version %s to WordPress SVN.\n' "$VERSION"
}

create_zip() {
    require_command rsync
    require_command zip

    mkdir -p "$OUTPUT_PATH"
    OUTPUT_PATH="$(cd "$OUTPUT_PATH" && pwd -P)"
    TEMP_PATH="$(mktemp -d "${TMPDIR:-/tmp}/technopay-release.XXXXXX")"

    rsync -a \
        --exclude='.git' \
        --exclude='.svn' \
        --exclude='.DS_Store' \
        --exclude='node_modules' \
        --exclude='.gitignore' \
        --exclude='*.log' \
        "$SOURCE_PATH/" "$TEMP_PATH/$PLUGIN_SLUG/"

    ZIP_NAME="$PLUGIN_SLUG-$VERSION.zip"
    ZIP_TEMP="$TEMP_PATH/$ZIP_NAME"
    ZIP_DESTINATION="$OUTPUT_PATH/$ZIP_NAME"

    (
        cd "$TEMP_PATH"
        zip -qr "$ZIP_TEMP" "$PLUGIN_SLUG"
    )

    rm -f "$ZIP_DESTINATION"
    mv "$ZIP_TEMP" "$ZIP_DESTINATION"

    printf 'Created ZIP: %s\n' "$ZIP_DESTINATION"
}

trap cleanup EXIT

printf 'TechnoPay plugin version: %s\n' "$VERSION"

if [ "$SVN_MODE" = "publish" ]; then
    publish_svn
else
    printf 'Skipped WordPress SVN publication.\n'
fi

create_zip
