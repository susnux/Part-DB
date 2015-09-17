#!/bin/sh
set -e

OUTPUT_DIR="../../js"
COMPILATION_LEVEL="ADVANCED_OPTIMIZATIONS"
# "WHITESPACE_ONLY" "SIMPLE_OPTIMIZATIONS" "ADVANCED_OPTIMIZATIONS"
REGISTERED_FILES[0]="./release/lib/dgrid/css/dgrid.css	dgrid/css"
REGISTERED_FILES[1]="./release/lib/dgrid/css/skins/claro.css	dgrid/css/skins"
REGISTERED_FILES[2]="./release/lib/dgrid/css/skins/images/row_back.png	dgrid/css/skins/images"
REGISTERED_FILES[3]="./release/lib/dgrid/css/images/ui-icons_222222_256x240.png	dgrid/css/images"
REGISTERED_FILES[4]="./release/lib/dojo/resources/blank.gif	dojo/resources"
REGISTERED_FILES[5]="./release/lib/dojo/resources/dnd.css	dojo/resources"
REGISTERED_FILES[6]="./release/lib/dojo/resources/images/dndMove.png	dojo/resources/images"
array() {
    if [ $# -eq 2 ]; then
		local num=0
        IFS='	' read -ra ADDR <<< "${REGISTERED_FILES[$1]}"
        for i in "${ADDR[@]}"; do
            if [ $num -eq "$2" ]; then
                echo "$i"
                return 0
            fi
            num=$(($num + 1))
        done
    else
		if [ $# -eq 3 ]; then
			local num=0
			local NEW=""
			IFS='	' read -ra ADDR <<< "${REGISTERED_FILES[$1]}"
			for i in "${ADDR[@]}"; do
				new="$i"
				if [ $num -eq "$2" ]; then
					new="$3"
				fi
					if [ $num -eq 0 ]; then
					NEW="$new"
				else
					NEW="$NEW	$new"
				fi
				num=$(($num + 1))
			done
			if [ $num -le "$2" ]; then
				while [ $num -lt "$2" ]; do
					NEW="$NEW	"
					num=$(($num + 1))
				done
				NEW="$NEW	$3"
			fi
			REGISTERED_FILES[$1]="$NEW"
			return 0
		else
			echo "Wrong usage of array" >&2
			return 2
		fi
	fi
}

register_file() {
	if [ $# -lt 2 ]; then
		echo "Too few arguments for register_file" >&2
		return 2
	fi
	local index=${#REGISTERED_FILES[@]}
	array $index 0 $1
	array $index 1 $2
}

compile_dojo() {
	echo -e "\e[31;1m"
	echo "************************************"
	echo "***    COMPILE DOJO & DGRID      ***"
	echo "************************************"
	echo -e "\e[0m"
	sh util/buildscripts/build.sh -p partdb.profile.js -r > /dev/null
	register_file "./release/lib/dojo/dojo.js" "dojo/"
	register_file "./release/lib/partdb/partdb.js" "partdb/"
}

compile_part_db() {
	if [ ! -f "release/lib/dojo/dojo.js" ] || [ ! -f "release/lib/partdb/partdb.js" ]; then
		compile_dojo
	fi
	echo -e "\e[31;1m"
	echo "************************************"
	echo "***       COMPILE PART-DB        ***"
	echo "************************************"
	echo -e "\e[0m"
	cmd="java -jar util/closureCompiler/compiler.jar"
	input_files[0]="Part-DB/basicTemplate.js"
	input_files[1]="Part-DB/Warehouse.js"
	input_files[2]="Part-DB/main.js"
	input_files=$(A=""; for f in ${input_files[@]}; do A="$A --js=$f"; done; echo "$A")
	$cmd --compilation_level=ADVANCED_OPTIMIZATIONS $input_files --js_output_file=/tmp/part-db-warnings.js
	rm /tmp/part-db-warnings.js
	$cmd --compilation_level="$COMPILATION_LEVEL" \
	$input_files \
	--third_party --externs=release/lib/dojo/dojo.js \
	--third_party --externs=release/lib/partdb/partdb.js \
	--js_output_file=./release/part-db.js >&2 2> /dev/null
	register_file "./release/part-db.js" ""
}

clean_build_space() {
	echo -e "\e[31;1m"
	echo "************************************"
	echo "***  CLEAN BUILD ENVIRONMENT     ***"
	echo "************************************"
	echo -e "\e[0m"
    if [ -d "./release" ]; then
		# Show only warnings / errors
        rm -rv ./release > /dev/null
        echo "Successfully removed ./release"
    fi
}

install_files() {
	echo -e "\e[31;1m"
	echo "************************************"
	echo "***    INSTALL FILES             ***"
	echo "************************************"
	echo -e "\e[0m"
	local index=$((${#REGISTERED_FILES[@]} - 1))
	for i in $(seq 0 $index); do
		if [ ! -d "$OUTPUT_DIR/$(array $i 1)" ]; then
			mkdir -pv "$OUTPUT_DIR/$(array $i 1)"
		fi
		install -v "$(array $i 0)" "$OUTPUT_DIR/$(array $i 1)"
	done
}

show_usage() {
	echo -e "\e[31;1m"
	echo "************************************"
	echo "***          USAGE               ***"
	echo "************************************"
	echo -e "\e[0m"
	echo -e "\tNo Command\tDo all"
	echo -e "\t--clean\t\tClean workspace"
	echo -e "\t--dojo\t\tCompile DOJO and dgrid"
	echo -e "\t--own\t\tCompile our own JS"
	echo -e "\t--install\tInstall compiled stuff"
}
clean=0
comp_1=0
comp_2=0
install=0
help=0
if [ $# -eq 0 ]; then
	clean=1
	comp_1=1
	comp_2=1
	install=1
else
	for cmd in $@; do
		case "$cmd" in
		"--install")
			install=1
			;;
		"--clean")
			clean=1
			;;
		"--dojo")
			comp_1=1
			;;
		"--own")
			comp_2=1
			;;
		*)
			help=1
			;;
		esac
	done
fi

if [ 1 -eq $help ]; then
	show_usage
	exit 0
fi
if [ 1 -eq $clean ]; then
	clean_build_space
fi
if [ 1 -eq $comp_1 ]; then
	compile_dojo
fi
if [ 1 -eq $comp_2 ]; then
	compile_part_db
fi
if [ 1 -eq $install ]; then
	install_files
fi
