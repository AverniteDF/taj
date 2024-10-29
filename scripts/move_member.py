import os
import sys
from typing import List, Tuple

MEMBERS_DIR = '../members/'
ARTICLES_DIR = '../articles/'

def is_positive_integer(s: str) -> bool:
    try:
        num = int(s)
        return num > 0
    except ValueError:
        return False

def get_arguments() -> Tuple[str, str]:
    if len(sys.argv) == 3:
        return sys.argv[1], sys.argv[2]
    else:
        arg1 = input("Folder to move: ")
        arg2 = input("Target ordinal: ")
        return arg1, arg2

def validate_arguments(arg1: str, arg2: str) -> Tuple[int, int]:
    if not is_positive_integer(arg1) or not is_positive_integer(arg2):
        raise ValueError("Both arguments must be positive integers")
    
    num1, num2 = int(arg1), int(arg2)
    if num1 == num2:
        raise ValueError("The two arguments must be different")
    
    if not os.path.isdir(MEMBERS_DIR + str(num1)):
        raise ValueError(f"No folder named '{num1}' exists in the current directory")
    
    return num1, num2

def rename_folders(arg1: int, arg2: int) -> List[Tuple[str, str]]:
    renames = []
    
    # If target folder doesn't exist, simple rename
    if not os.path.isdir(MEMBERS_DIR + str(arg2)):
        os.rename(MEMBERS_DIR + str(arg1), MEMBERS_DIR + str(arg2))
        renames.append((str(arg1), str(arg2)))
        return renames
    
    # Temporary rename of source folder
    temp_name = f"{arg1}-{arg2}"
    os.rename(MEMBERS_DIR + str(arg1), MEMBERS_DIR + temp_name)
    renames.append((str(arg1), temp_name))

    if arg1 < arg2:
        # Shift continuous downstream series up one
        i = arg1 + 1
        while i <= arg2 and os.path.isdir(MEMBERS_DIR + str(i)):
            os.rename(MEMBERS_DIR + str(i), MEMBERS_DIR + str(i - 1))
            renames.append((str(i), str(i - 1)))
            i += 1
    else:
        # Shift continuous upstream series down one
        i = arg1 - 1
        while i >= arg2 and os.path.isdir(MEMBERS_DIR + str(i)):
            os.rename(MEMBERS_DIR + str(i), MEMBERS_DIR + str(i + 1))
            renames.append((str(i), str(i + 1)))
            i -= 1
    
    # Finally, rename the temporary folder to target
    os.rename(MEMBERS_DIR + temp_name, MEMBERS_DIR + str(arg2))
    renames.append((temp_name, str(arg2)))
    
    return renames

def update_article_authors(renames: List[Tuple[str, str]]) -> None:
    # Create a mapping of old_id -> new_id, excluding temporary renames
    id_updates = {old: new for old, new in renames 
                 if not ('-' in old or '-' in new)}
    
    # Walk through all files in articles directory
    for root, _, files in os.walk(ARTICLES_DIR):
        for filename in files:
            if not (filename.endswith('.txt') and filename[:-4].isdigit()):
                continue
                
            filepath = os.path.join(root, filename)
            
            # Read first few lines of the file
            needs_update = False
            header_lines = []
            
            with open(filepath, 'r', encoding='utf-8') as f:
                for _ in range(7):  # Read first 7 lines
                    line = f.readline()
                    if not line:
                        break
                    
                    if line.startswith('AUTHOR='):
                        old_author = line.strip().split('=')[1]
                        if old_author in id_updates:
                            new_author = id_updates[old_author]
                            line = f'AUTHOR={new_author}\n'
                            needs_update = True
                    
                    header_lines.append(line)
                
                # Read the rest of the file if we need to update
                if needs_update:
                    remaining_content = f.read()
            
            # Write updates if needed
            if needs_update:
                with open(filepath, 'w', encoding='utf-8') as f:
                    f.writelines(header_lines)
                    f.write(remaining_content)
                    print(f"Updated author in {filepath}: {old_author} -> {new_author}")

def main():
    try:
        # Get and validate arguments
        arg1, arg2 = get_arguments()
        num1, num2 = validate_arguments(arg1, arg2)
        
        # Perform the renaming
        renames = rename_folders(num1, num2)
        
        # Print the renaming operations (for debugging/logging)
        print("\nFolder renaming operations performed:")
        for old_name, new_name in renames:
            print(f"Renamed: {old_name} -> {new_name}")

        # Update article authors
        print("\nUpdating article authors...")
        update_article_authors(renames)
            
    except ValueError as e:
        print(f"Error: {str(e)}")
    except Exception as e:
        print(f"An unexpected error occurred: {str(e)}")

if __name__ == "__main__":
    main()
    os.system("pause")