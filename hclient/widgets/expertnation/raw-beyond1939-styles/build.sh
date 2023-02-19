# Build CSS file from source
sass scss/style.scss src/beyond1939_style.css --style=compressed

# Copy built files to heurist repo
cp -f src/beyond1939_style.css* ~/github/heurist/hclient/widgets/expertnation/
cp -f src/cmsTemplate_Beyond1939.php ~/github/heurist/hclient/widgets/cms/templates/
cp -f logo/* ~/github/heurist/hclient/widgets/expertnation/

# Commit
cd ~/github/heurist/
git pull upstream h6dev
git add hclient/widgets/expertnation/*
git add hclient/widgets/cms/templates/cmsTemplate_Beyond1939.php
git commit -m "new version of Beyond 1939 stylings"
git push