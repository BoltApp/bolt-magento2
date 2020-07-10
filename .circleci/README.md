# Overview of Custom Circle CI Setup

## Customization branch auto rebase
The circle ci workflow _auto-rebase_ will be triggered on every PR merge to master and try to auto-rebase the branches listed in _.circlci/scripts/auto-rebase-branches.txt_ against master one by one. 
The results of rebasing will be pushed to ci/<branch-name> 

When a rebase process fails (eg: there is a conflict that needs to resolve manually) for a specified branch, it would try to recover and continue rebasing the other listed branches.
In the end of the job, it prints out a summary about which branches failed to auto-rebase.

To make your branch auto-rebase on master, simply append the branch name to _.circlci/scripts/auto-rebase-branches.txt_ in the newline separated format.