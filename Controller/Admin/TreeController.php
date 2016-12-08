<?php

namespace Netgen\TagsBundle\Controller\Admin;

use eZ\Bundle\EzPublishCoreBundle\Controller;
use eZ\Publish\API\Repository\ContentTypeService;
use Netgen\TagsBundle\API\Repository\TagsService;
use Netgen\TagsBundle\API\Repository\Values\Tags\Tag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Translation\TranslatorInterface;

class TreeController extends Controller
{
    /**
     * @var \Netgen\TagsBundle\API\Repository\TagsService
     */
    protected $tagsService;

    /**
     * @var \eZ\Publish\API\Repository\ContentTypeService
     */
    protected $contentTypeService;

    /**
     * @var \Symfony\Component\Translation\TranslatorInterface
     */
    protected $translator;

    /**
     * TreeController constructor.
     *
     * @param \Netgen\TagsBundle\API\Repository\TagsService $tagsService
     * @param \Symfony\Component\Translation\TranslatorInterface $translator
     * @param \eZ\Publish\API\Repository\ContentTypeService $contentTypeService
     */
    public function __construct(TagsService $tagsService, ContentTypeService $contentTypeService, TranslatorInterface $translator)
    {
        $this->tagsService = $tagsService;
        $this->contentTypeService = $contentTypeService;
        $this->translator = $translator;
    }

    /**
     * Returns JSON string containing all children tags for given tag.
     * It is called in AJAX request from jsTree Javascript plugin to render tree with tags.
     * It supports lazy loading; when a tag is clicked in a tree, it calls this method to fetch it's children.
     *
     * @param \Netgen\TagsBundle\API\Repository\Values\Tags\Tag|null $tag
     * @param bool $isRoot
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getChildrenAction(Tag $tag = null, $isRoot = false)
    {
        $childrenTags = $this->tagsService->loadTagChildren($tag);

        $result = array();

        if ((bool) $isRoot) {
            if ($tag === null) {
                $result = array(
                    array(
                        'id' => '0',
                        'parent' => '#',
                        'text' => $this->translator->trans(
                            'tag.tree.top_level',
                            array(),
                            'eztags_admin'
                        ),
                        'children' => true,
                        'state' => array(
                            'opened' => true,
                        ),
                        'a_attr' => array(
                            'href' => $this->generateUrl(
                                'netgen_tags_admin_dashboard_index'
                            ),
                            'class' => $tag !== null && $this->hasSubtreeLimitations($tag) ? 'has-limitations' : '',
                        ),
                        'data' => array(
                            'add_child' => array(
                                'url' => $this->generateUrl(
                                    'netgen_tags_admin_tag_add_select',
                                    array(
                                        'parentId' => 0,
                                    )
                                ),
                                'text' => $this->translator->trans(
                                    'tag.tree.add_child',
                                    array(),
                                    'eztags_admin'
                                ),
                            ),
                        ),
                    ),
                );
            } else {
                $result = $this->getTagTreeData($tag, $isRoot);
            }
        } else {
            foreach ($childrenTags as $tag) {
                $result[] = $this->getTagTreeData($tag, $isRoot);
            }
        }

        return (new JsonResponse())->setData($result);
    }

    /**
     * Generates data, for given tag, which will be converted to JSON:.
     *
     * @param \Netgen\TagsBundle\API\Repository\Values\Tags\Tag $tag
     * @param bool $isRoot
     *
     * @return array
     */
    protected function getTagTreeData(Tag $tag, $isRoot = false)
    {
        $synonymCount = $tag === null ?
            0 :
            $this->tagsService->getTagSynonymCount($tag);

        return array(
            'id' => $tag->id,
            'parent' => $isRoot ? '#' : $tag->parentTagId,
            'text' => $synonymCount > 0 ? $tag->keyword . ' (+' . $synonymCount . ')' : $tag->keyword,
            'children' => $this->tagsService->getTagChildrenCount($tag) > 0,
            'a_attr' => array(
                'href' => $this->generateUrl(
                    'netgen_tags_admin_tag_show',
                    array(
                        'tagId' => $tag->id,
                    )
                ),
                'class' => $tag !== null && $this->hasSubtreeLimitations($tag) ? 'has-limitations' : '',
            ),
            'state' => array(
                'opened' => $isRoot,
            ),
            'data' => array(
                'add_child' => array(
                    'url' => $this->generateUrl(
                        'netgen_tags_admin_tag_add_select',
                        array(
                            'parentId' => $tag->id,
                        )
                    ),
                    'text' => $this->translator->trans(
                        'tag.tree.add_child',
                        array(),
                        'eztags_admin'
                    ),
                ),
                'update_tag' => array(
                    'url' => $this->generateUrl(
                        'netgen_tags_admin_tag_update_select',
                        array(
                            'tagId' => $tag->id,
                        )
                    ),
                    'text' => $this->translator->trans(
                        'tag.tree.update_tag',
                        array(),
                        'eztags_admin'
                    ),
                ),
                'delete_tag' => array(
                    'url' => $this->generateUrl(
                        'netgen_tags_admin_tag_delete',
                        array(
                            'tagId' => $tag->id,
                        )
                    ),
                    'text' => $this->translator->trans(
                        'tag.tree.delete_tag',
                        array(),
                        'eztags_admin'
                    ),
                ),
                'merge_tag' => array(
                    'url' => $this->generateUrl(
                        'netgen_tags_admin_tag_merge',
                        array(
                            'tagId' => $tag->id,
                        )
                    ),
                    'text' => $this->translator->trans(
                        'tag.tree.merge_tag',
                        array(),
                        'eztags_admin'
                    ),
                ),
                'add_synonym' => array(
                    'url' => $this->generateUrl(
                        'netgen_tags_admin_synonym_add_select',
                        array(
                            'mainTagId' => $tag->id,
                        )
                    ),
                    'text' => $this->translator->trans(
                        'tag.tree.add_synonym',
                        array(),
                        'eztags_admin'
                    ),
                ),
                'convert_tag' => array(
                    'url' => $this->generateUrl(
                        'netgen_tags_admin_tag_convert',
                        array(
                            'tagId' => $tag->id,
                        )
                    ),
                    'text' => $this->translator->trans(
                        'tag.tree.convert_tag',
                        array(),
                        'eztags_admin'
                    ),
                ),
            ),
        );
    }

    /**
     * Returns if given tag has subtree limitations.
     *
     * @param \Netgen\TagsBundle\API\Repository\Values\Tags\Tag $tag
     *
     * @return bool
     */
    protected function hasSubtreeLimitations(Tag $tag)
    {
        foreach ($this->contentTypeService->loadContentTypeGroups() as $contentTypeGroup) {
            foreach ($this->contentTypeService->loadContentTypes($contentTypeGroup) as $contentType) {
                foreach ($contentType->getFieldDefinitions() as $fieldDefinition) {
                    if ($fieldDefinition->fieldTypeIdentifier === 'eztags') {
                        if ($fieldDefinition->getFieldSettings()['subTreeLimit'] === $tag->id) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }
}
